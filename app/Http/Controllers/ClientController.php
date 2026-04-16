<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    private function normalizeTelephone(?string $telephone): ?string
    {
        if (!$telephone) return null;

        // garder uniquement les chiffres
        $telephone = preg_replace('/\D/', '', $telephone);

        // retirer indicatif Sénégal si présent
        if (str_starts_with($telephone, '221')) {
            $telephone = substr($telephone, -9);
        }

        return $telephone;
    }

    // ============================================================
    // LISTE DES CLIENTS
    public function index(Request $request)
    {
        $query = Client::withCount('commandes')->latest();

        // filtre type client
        if ($request->filled('type_client')) {
            $query->where('type_client', $request->type_client);
        }

        // FILTRE RECHERCHE
        if ($request->filled('search')) {
            $s = $request->search;

            $query->where(function ($q) use ($s) {
                $q->where('nom', 'like', "%{$s}%")
                    ->orWhere('telephone_hash', hash('sha256', $s))
                    ->orWhere('entreprise', 'like', "%{$s}%")
                    ->orWhere('adresse_hash', hash('sha256', $s));
            });
        }

        return $query->paginate(10);
    }

    // ============================================================
    // CRÉATION
    // ============================================================
    public function store(Request $request)
    {
        $request->merge([
            'telephone' => $this->normalizeTelephone($request->telephone),
        ]);

        $rules = [
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'entreprise' => 'nullable|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|digits:13',
            'contact' => 'nullable|string',
            'solde' => 'nullable|numeric',
        ];

        // Si responsable → client spécial avec téléphone requis, numérique et unique
        if (Auth::user()->role === 'responsable') {
            $rules['telephone'] = [
                'required',
                'regex:/^[0-9]+$/',
                function ($attribute, $value, $fail) {
                    $exists = Client::where('telephone', $value)
                        ->where('type_client', 'special')
                        ->exists();

                    if ($exists) {
                        $fail('Un client spécial avec ce téléphone existe déjà.');
                    }
                }
            ];
        } else {
            $rules['telephone'] = 'nullable|regex:/^[0-9]+$/';
        }

        $data = $request->validate($rules);

        // Définir le type_client selon le rôle
        $data['type_client'] = Auth::user()->role === 'responsable' ? 'special' : 'normal';
        $data['numero_cni_hash'] = hash('sha256', $data['numero_cni']);
        //si contact existe, hash le contact
        if ($data['contact']) {
            $data['contact_hash'] = hash('sha256', $data['contact']);
        }

        $data['adresse_hash'] = hash('sha256', $data['adresse']);

        $client = Client::create($data);
        return response()->json($client, 201);
    }

    // ============================================================
    // AFFICHER
    // ============================================================
    public function show(string $id)
    {
        return Client::findOrFail($id);
    }

    // ============================================================
    // MODIFICATION
    // ============================================================
    public function update(Request $request, string $id)
    {
        $request->merge([
            'telephone' => $this->normalizeTelephone($request->telephone),
        ]);

        $client = Client::findOrFail($id);
        $isResponsable = Auth::user()->role === 'responsable';

        $rules = [
            'nom' => 'sometimes|string',
            'entreprise' => 'nullable|string',
            'prenom' => 'nullable|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|digits:13',
            'contact' => 'nullable|string',
            'solde' => 'nullable|numeric',
        ];

        // Règle d'unicité pour téléphone (seulement pour clients spéciaux)
        $rules['telephone'] = [
            'nullable',
            'regex:/^[0-9]+$/',
            function ($attribute, $value, $fail) use ($client) {
                if ($value) {
                    $exists = Client::where('telephone', $value)
                        ->where('type_client', 'special')
                        ->where('id', '!=', $client->id)
                        ->exists();

                    if ($exists) {
                        $fail('Un client spécial avec ce téléphone existe déjà.');
                    }
                }
            }
        ];

        $data = $request->validate($rules);
        $data['entreprise'] = $data['entreprise'] ?? null;

        // Seul le responsable peut changer type_client
        if ($isResponsable && $request->filled('type_client')) {
            $client->type_client = $request->type_client;
        }

        $data['numero_cni_hash'] = hash('sha256', $data['numero_cni']);
        
        if($data['contact']){
            //si contact existe, hash le contact
            $data['contact_hash'] = hash('sha256', $data['contact']);
        }

        $data['adresse_hash'] = hash('sha256', $data['adresse']);

        $client->update($data);

        return $client;
    }

    // ============================================================
    // SUPPRESSION
    // ============================================================
    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);

        // On applique la règle seulement aux clients spéciaux
        if ($client->type_client === 'special') {

            $hasDebt = $client->commandes()
                ->where('statut', '!=', 'annulee')
                ->whereNotNull('total')
                ->whereRaw("
                        total > (
                            SELECT COALESCE(SUM(montant),0)
                            FROM paiements
                            WHERE paiements.commande_id = commandes.id
                        )
                    ")
                ->exists();

            if ($hasDebt) {
                return response()->json([
                    'message' => "Impossible de supprimer ce client : il a encore une dette."
                ], 422);
            }
        }

        $client->delete();

        return response()->noContent();
    }

    // ============================================================
    // TRANCHES
    // ============================================================
    public function paiementsTranches(Request $request, string $clientId)
    {
        $client = Client::findOrFail($clientId);

        $paginator = Commande::with(['paiements' => function ($q) {
            $q->orderBy('date');
        }])
            ->where('client_id', $client->id)
            ->orderByDesc('date')
            ->paginate(50);

        $transformed = $paginator->getCollection()->map(function (Commande $cmd) {
            $totalPaye = $cmd->montantPaye();
            $reste = $cmd->resteAPayer();

            return [
                'commande_id' => $cmd->id,
                'date' => $cmd->date,
                'statut' => $cmd->statut,
                'total' => $cmd->total,
                'total_paye' => $totalPaye,
                'reste' => $reste,
                'tranches' => $cmd->paiements->map(function (Paiement $p) {
                    return [
                        'paiement_id' => $p->id,
                        'montant' => $p->montant,
                        'type_paiement' => $p->type_paiement,
                        'date' => $p->date,
                        'reste_du' => $p->reste_du,
                    ];
                }),
            ];
        });

        $paginator->setCollection($transformed);

        $commandeIds = Commande::where('client_id', $client->id)->pluck('id');
        $montantTotal = Commande::where('client_id', $client->id)->sum('total');
        $totalPayeGlobal = Paiement::whereIn('commande_id', $commandeIds)->sum('montant');
        $resteTotal = max(0, $montantTotal - $totalPayeGlobal);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'nom' => $client->nom,
                'entreprise' => $client->entreprise,
                'type_client' => $client->type_client,
            ],
            'summary' => [
                'commandes_count' => $commandeIds->count(),
                'montant_total' => $montantTotal,
                'total_paye' => $totalPayeGlobal,
                'reste_total' => $resteTotal,
            ],
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function stats()
    {
        return response()->json([
            'total' => Client::count(),
            'speciaux' => Client::where('type_client', 'special')->count(),
            'normaux' => Client::where('type_client', 'normal')->count(),
        ]);
    }

    // ============================================================
    // LISTE COMPLETE CLIENTS SPECIAUX (sans pagination)
    // ============================================================
    public function allSpeciaux()
    {
        return Client::where('type_client', 'special')
            ->select('id', 'nom', 'prenom', 'telephone')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    public function clientsDette(Request $request)
    {
        try {

            $clients = Client::where('type_client', 'special')
                ->whereHas('commandes', function ($q) {
                    $q->where('statut', '!=', 'annulee')
                        ->whereRaw("
                                    total > (
                                        SELECT COALESCE(SUM(montant),0)
                                        FROM paiements
                                        WHERE paiements.commande_id = commandes.id
                                    )
                                ");
                })
                ->withCount(['commandes as commandes_en_dette_count' => function ($q) {
                    $q->where('statut', '!=', 'annulee')
                        ->whereRaw("
                                    total > (
                                        SELECT COALESCE(SUM(montant),0)
                                        FROM paiements
                                        WHERE paiements.commande_id = commandes.id
                                    )
                                ");
                }])
                ->with(['commandes' => function ($q) {
                    $q->where('statut', '!=', 'annulee')
                        ->with('paiements');
                }])
                ->latest()
                ->paginate(10);

            // Transformation
            $clients->getCollection()->transform(function ($client) {

                $totalTTC = $client->commandes->sum('total');

                $totalPaye = $client->commandes
                    ->flatMap(fn($c) => $c->paiements)
                    ->sum('montant');

                $dette = max(0, $totalTTC - $totalPaye);

                return [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'prenom' => $client->prenom,
                    'telephone' => $client->telephone,
                    'commandes_en_dette_count' => $client->commandes_en_dette_count,
                    'totalTTC' => $totalTTC,
                    'totalPaye' => $totalPaye,
                    'dette' => $dette,
                ];
            });

            return response()->json($clients);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Erreur lors de la récupération des clients en dette.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function resumeFinancierClientsSpeciaux(Request $request)
    {
        $etat = $request->get('etat', 'tous');
        $search = $request->get('search');

        $query = Client::where('clients.type_client', 'special')
            ->select(
                'clients.id',
                'clients.nom',
                'clients.prenom',
                'clients.telephone',
                'clients.adresse',
            )
            ->selectRaw('COALESCE(SUM(commandes.total),0) as totalTTC')
            ->selectRaw('COALESCE(SUM(paiements.montant),0) as totalPaye')
            ->selectRaw('
                        COALESCE(SUM(commandes.total),0)
                        - COALESCE(SUM(paiements.montant),0)
                        as dette
                    ')
            ->selectRaw('COUNT(DISTINCT commandes.id) as commandes_count')
            ->selectRaw('COUNT(DISTINCT CASE
                        WHEN commandes.total > (
                            SELECT COALESCE(SUM(p2.montant),0)
                            FROM paiements p2
                            WHERE p2.commande_id = commandes.id
                        )
                        THEN commandes.id
                    END) as commandes_en_dette_count')

            ->leftJoin('commandes', function ($join) {
                $join->on('clients.id', '=', 'commandes.client_id')
                    ->where('commandes.statut', '!=', 'annulee');
            })
            ->leftJoin('paiements', 'commandes.id', '=', 'paiements.commande_id')

            ->groupBy(
                'clients.id',
                'clients.nom',
                'clients.prenom',
                'clients.telephone',
                'clients.adresse',
            );

        // RECHERCHE
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('clients.nom', 'like', "%{$search}%")
                    ->orWhere('clients.prenom', 'like', "%{$search}%")
                    ->orWhere('clients.telephone_hash', hash('sha256', $search));
            });
        }

        // FILTRE ÉTAT basé sur dette SQL
        if ($etat === 'endettes') {
            $query->having('dette', '>', 0);
        }

        if ($etat === 'a_jour') {
            $query->having('dette', '<=', 0);
        }
        $query->orderByDesc('clients.id');
        $clients = $query->paginate(10);

        // cast propre
        $clients->getCollection()->transform(function ($client) {
            $client->totalTTC = (float)$client->totalTTC;
            $client->totalPaye = (float)$client->totalPaye;
            $client->dette = (float)$client->dette;
            return $client;
        });

        return response()->json($clients);
    }
    // ============================================================
    // STATS DÉTAIL CLIENT SPECIAL
    // ============================================================
    public function statsClient(string $clientId)
    {
        $client = Client::findOrFail($clientId);

        $commandes = Commande::where('client_id', $client->id)
            ->where('statut', '!=', 'annulee')
            ->get();

        $totalTTC = $commandes->sum('total');

        $commandeIds = $commandes->pluck('id');

        $totalPaye = Paiement::whereIn('commande_id', $commandeIds)
            ->sum('montant');

        $dette = max(0, $totalTTC - $totalPaye);

        return response()->json([
            'nb' => $commandes->count(),
            'totalTTC' => (float) $totalTTC,
            'totalPaye' => (float) $totalPaye,
            'dette' => (float) $dette,
        ]);
    }
}
