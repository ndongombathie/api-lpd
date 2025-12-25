<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Decaissement;
use App\Models\DecaissementLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DecaissementController extends Controller
{
    /**
     * GET /api/decaissements
     * Liste des décaissements (format camelCase pour le front)
     */
    public function index(Request $request)
    {
        $query = Decaissement::with('lignes')
            ->orderBy('date_prevue', 'desc')
            ->orderBy('created_at', 'desc');

        // Filtres optionnels
        if ($statut = $request->query('statut')) {
            if (in_array($statut, ['en attente', 'validé', 'refusé'])) {
                $query->where('statut', $statut);
            }
        }

        if ($start = $request->query('startDate')) {
            $query->whereDate('date_prevue', '>=', $start);
        }

        if ($end = $request->query('endDate')) {
            $query->whereDate('date_prevue', '<=', $end);
        }

        if ($search = trim($request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('motif_global', 'like', "%{$search}%")
                    ->orWhereHas('lignes', function ($sub) use ($search) {
                        $sub->where('libelle', 'like', "%{$search}%");
                    });
            });
        }

        $decaissements = $query->get();

        // Normalisation → camelCase
        $data = $decaissements->map(function (Decaissement $d) {
            return [
                'id'            => $d->id,
                'reference'     => $d->reference,
                'datePrevue'    => optional($d->date_prevue)->toDateString(),
                'motifGlobal'   => $d->motif_global,
                'methodePrevue' => $d->methode_prevue,
                'statut'        => $d->statut,
                'montantTotal'  => $d->montant_total,
                'lignes'        => $d->lignes->map(function (DecaissementLigne $l) {
                    return [
                        'id'      => $l->id,
                        'libelle' => $l->libelle,
                        'montant' => $l->montant,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * POST /api/decaissements
     * Création d’une nouvelle demande de décaissement
     */
    public function store(Request $request)
    {
        try {
            /*
             * On récupère les champs en tolérant à la fois :
             *   - motifGlobal / methodePrevue / datePrevue
             *   - motif_global / methode_prevue / date_prevue
             */
            $raw = [
                'motif_global'   => $request->input('motif_global', $request->input('motifGlobal')),
                'methode_prevue' => $request->input('methode_prevue', $request->input('methodePrevue')),
                'date_prevue'    => $request->input('date_prevue', $request->input('datePrevue')),
                'lignes'         => $request->input('lignes', $request->input('decaissement_lignes', [])),
            ];

            // ✅ Validation sur notre tableau $raw
            $validator = Validator::make($raw, [
                'motif_global'     => ['required', 'string', 'max:255'],
                'methode_prevue'   => ['required', 'string', 'max:100'],
                'date_prevue'      => ['required', 'date'],
                'lignes'           => ['required', 'array', 'min:1'],
                'lignes.*.libelle' => ['required', 'string', 'max:255'],
                'lignes.*.montant' => ['required', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Données invalides.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // 🔒 Transaction création + lignes
            $decaissement = DB::transaction(function () use ($validated) {
                // Référence
                $reference = 'DEC-' . now()->format('Y') . '-' . random_int(1000, 9999);

                // En-tête (BDD en snake_case, colonnes UUID)
                $dec = Decaissement::create([
                    'reference'      => $reference,
                    'date_prevue'    => $validated['date_prevue'],
                    'motif_global'   => $validated['motif_global'],
                    'methode_prevue' => $validated['methode_prevue'],
                    'statut'         => 'en attente',
                    'montant_total'  => 0,
                    'demandeur_id'   => (string) Auth::id(),   // UUID
                    'traite_par_id'  => null,
                ]);

                // Lignes + total
                $total = 0;

                foreach ($validated['lignes'] as $ligneData) {
                    $ligne = $dec->lignes()->create([
                        'libelle' => $ligneData['libelle'],
                        'montant' => (int) $ligneData['montant'],
                    ]);

                    $total += $ligne->montant;
                }

                $dec->update(['montant_total' => $total]);

                return $dec->fresh('lignes');
            });

            // Réponse format camelCase pour le front
            $payload = [
                'id'            => $decaissement->id,
                'reference'     => $decaissement->reference,
                'datePrevue'    => optional($decaissement->date_prevue)->toDateString(),
                'motifGlobal'   => $decaissement->motif_global,
                'methodePrevue' => $decaissement->methode_prevue,
                'statut'        => $decaissement->statut,
                'montantTotal'  => $decaissement->montant_total,
                'lignes'        => $decaissement->lignes->map(function (DecaissementLigne $l) {
                    return [
                        'id'      => $l->id,
                        'libelle' => $l->libelle,
                        'montant' => $l->montant,
                    ];
                })->values(),
            ];

            return response()->json($payload, 201);
        } catch (\Throwable $e) {
            // 📝 Log propre avec le facade importé
            Log::error('Erreur création décaissement', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erreur interne lors de la création du décaissement.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PATCH /api/decaissements/{decaissement}/statut
     * (validé / refusé / en attente)
     */
    public function updateStatut(Request $request, Decaissement $decaissement)
    {
        $data = $request->validate([
            'statut' => ['required', 'in:en attente,validé,refusé'],
        ]);

        $decaissement->update([
            'statut'        => $data['statut'],
            'traite_par_id' => (string) (Auth::id() ?? $decaissement->traite_par_id),
        ]);

        $decaissement->load('lignes');

        $payload = [
            'id'            => $decaissement->id,
            'reference'     => $decaissement->reference,
            'datePrevue'    => optional($decaissement->date_prevue)->toDateString(),
            'motifGlobal'   => $decaissement->motif_global,
            'methodePrevue' => $decaissement->methode_prevue,
            'statut'        => $decaissement->statut,
            'montantTotal'  => $decaissement->montant_total,
            'lignes'        => $decaissement->lignes->map(function (DecaissementLigne $l) {
                return [
                    'id'      => $l->id,
                    'libelle' => $l->libelle,
                    'montant' => $l->montant,
                ];
            })->values(),
        ];

        return response()->json($payload);
    }
}
