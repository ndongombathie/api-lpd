<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DecaissementController extends Controller
{
    // ==========================================================
    // 📥 LISTE + KPI (pagination backend + stats globales)
    // ==========================================================
    public function index(Request $request)
    {
        $query = Decaissement::query()
    ->with(['lignes', 'caissier'])
    ->latest();

        // Filtre statut
        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        // Recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('motif_global', 'like', "%$search%")
                  ->orWhere('motif', 'like', "%$search%")
                  ->orWhere('libelle', 'like', "%$search%");
            });
        }

        // Dates
        if ($request->filled('start_date')) {
            $query->whereDate('date_prevue', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date_prevue', '<=', $request->end_date);
        }

        $paginator = $query->paginate(10);

        // -----------------------------
        // 🔥 Mapping vers le format React
        // -----------------------------
        $mapped = $paginator->getCollection()->map(function ($d) {

            $motif = $d->motif_global ?? $d->motif;
            $date  = $d->date_prevue ?? $d->date;

            $total = $d->montant_total > 0
                ? $d->montant_total
                : ($d->lignes->sum('montant') ?: $d->montant);

            $lignes = $d->lignes->count()
                ? $d->lignes
                : collect([
                    ['libelle' => $d->libelle ?? $motif, 'montant' => $total]
                ]);

            return [
                'id' => $d->id,
                'motifGlobal' => $motif,
                'methodePrevue' => $d->methode_prevue ?? $d->methode_paiement,
                'datePrevue' => $date,
                'montantTotal' => (int) $total,
                'statut' => $d->statut,

                'caissier' => $d->caissier ? [
                    'id' => $d->caissier->id,
                    'nom' => $d->caissier->nom,
                    'prenom' => "",
                ] : null,

                'lignes' => $lignes->map(fn ($l) => [
                    'libelle' => $l['libelle'] ?? $l->libelle,
                    'montant' => (int) ($l['montant'] ?? $l->montant),
                ])->values(),
            ];

        });

        $paginator->setCollection($mapped);

        // -----------------------------
        // 📊 KPI globaux (toute la base)
        // -----------------------------
        $stats = [
            'total' => Decaissement::count(),
            'montant_total' => Decaissement::sum('montant_total'),

            'valides' => Decaissement::where('statut', 'validé')->count(),
            'montant_valides' => Decaissement::where('statut', 'validé')->sum('montant_total'),

            'annules' => Decaissement::where('statut', 'refusé')->count(),
            'montant_annules' => Decaissement::where('statut', 'refusé')->sum('montant_total'),

            'attente' => Decaissement::where('statut', 'en_attente')->count(),
            'montant_attente' => Decaissement::where('statut', 'en_attente')->sum('montant_total'),
        ];

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'stats' => $stats,
        ]);
    }

    // ==========================================================
    // ➕ Création décaissement (Responsable / Caissier)
    // ==========================================================
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // -----------------------------
            // RESPONSABLE
            // -----------------------------
            if ($user->role === 'responsable') {

            $data = $request->validate([
                'motifGlobal' => 'required|string|min:3',
                'methodePrevue' => 'required|string',
                'datePrevue' => 'required|date',
                'caissier_id' => 'required|exists:users,id',
                'lignes' => 'required|array|min:1',
                'lignes.*.montant' => 'required|numeric|min:1',
            ]);



                $total = collect($data['lignes'])->sum('montant');

                $decaissement = Decaissement::create([
                    'user_id' => $user->id,
                    'caissier_id' => $data['caissier_id'],
                    'statut' => 'en_attente',

                    'motif' => $data['motifGlobal'],
                    'libelle' => 'Demande Responsable',
                    'montant' => $total,
                    'methode_paiement' => $data['methodePrevue'],
                    'date' => $data['datePrevue'],

                    'motif_global' => $data['motifGlobal'],
                    'methode_prevue' => $data['methodePrevue'],
                    'date_prevue' => $data['datePrevue'],
                    'montant_total' => $total,
                ]);

                foreach ($data['lignes'] as $ligne) {
                    DB::table('decaissement_lignes')->insert([
                        'decaissement_id' => $decaissement->id,
                        'libelle' => $data['motifGlobal'], // 🔥 auto
                        'montant' => $ligne['montant'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $decaissement->load(['lignes', 'caissier']);

                return response()->json([
                    'decaissement' => $decaissement,
                    'lignes' => $data['lignes'],
                ], 201);
            }

            // -----------------------------
            // CAISSIER (ancien flux)
            // -----------------------------
            $validated = $request->all();
            $validated['user_id'] = $user->id;

            $decaissement = Decaissement::create($validated);

            return response()->json($decaissement, 201);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur création décaissement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==========================================================
    // 🔁 Changement de statut
    // ==========================================================
    public function updateStatusDecaissement(Request $request, Decaissement $decaissement)
    {
        $request->validate([
            'statut' => 'required|in:validé,refusé,en_attente',
        ]);

        $decaissement->update([
            'statut' => $request->statut,
            'caissier_id' => Auth::id(),
        ]);

        return response()->json($decaissement);
    }

    // ==========================================================
    // ❌ Suppression
    // ==========================================================
    public function destroy(Decaissement $decaissement)
    {
        $decaissement->delete();
        return response()->json(null, 204);
    }

    // ==========================================================
    // 📤 EXPORT COMPLET (toutes les pages, mêmes filtres)
    // ==========================================================
    public function exportAll(Request $request)
    {
        $query = Decaissement::query()
        ->with(['lignes', 'caissier'])
        ->latest();
        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('motif_global', 'like', "%$s%")
                  ->orWhere('motif', 'like', "%$s%")
                  ->orWhere('libelle', 'like', "%$s%");
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date_prevue', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date_prevue', '<=', $request->end_date);
        }

        return response()->json(
            $query->get()->map(function ($d) {

                $motif = $d->motif_global ?? $d->motif;
                $date  = $d->date_prevue ?? $d->date;

                $total = (int) (
                    $d->montant_total
                    ?? $d->lignes->sum('montant')
                    ?? $d->montant
                    ?? 0
                );


                return [
                    'datePrevue' => $date,
                    'motifGlobal' => $motif,
                    'caissier' => $d->caissier ? [
                    'id' => $d->caissier->id,
                    'nom' => $d->caissier->nom,
                    'prenom' => "",
                ] : null,

                    'methodePrevue' => $d->methode_prevue ?? $d->methode_paiement,
                    'statut' => $d->statut,
                    'montantTotal' => (int) $total,
                ];
            })
        );
    }
}
