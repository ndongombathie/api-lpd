<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Events\CommandeValidee;
use App\Events\CommandeAnnulee;
use App\Models\StockBoutique;
use App\Models\TransfertEnAttente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Commande::with([
                'details.produit',
                'client',
                'vendeur',
                'paiements'
            ])->where('vendeur_id', Auth::user()->id)->latest();

                if ($request->filled('client_id')) {
                    $client_id = $request->client_id;
                    $query->whereHas('client', function ($q) use ($client_id) {
                        $q->where('id', $client_id);
                    });
                }

                if ($request->filled('type_client')) {
                    $typeClient = $request->type_client;

                    $query->whereHas('client', function ($q) use ($typeClient) {
                        $q->where('type_client', $typeClient);
                    });
                }
            // 🔎 Filtre statut
            if ($request->filled('statut')) {
                $query->where('statut', $request->statut);
            }

            // 📅 Filtre période
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // 🔍 Recherche
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($qc) use ($search) {
                        $qc->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    });
                });
            }
            
            $paginator = $query->paginate(10);

            $paginator->getCollection()->transform(function ($commande) {

                $totalPaye = $commande->paiements->sum('montant');

                $commande->montant_paye = $totalPaye;
                $commande->reste_a_payer = max(0, $commande->total - $totalPaye);

                return $commande;
            });

            return response()->json($paginator);
            } catch (\Throwable $th) {
                return response()->json([
                    'message' => 'Erreur lors de la récupération des commandes',
                    'error' => $th->getMessage(),
                ], 500);
            }
    }

    public function getCommandesEnAttente(Request $request){
        try {
            $search = $request->input('search', '');

            $query = Commande::query()
                ->whereNotNull('montant_a_encaisser')
                ->where('montant_a_encaisser', '>', 0)
                ->whereIn('statut', ['attente', 'partiellement_payee'])
                ->with(['details.produit', 'client', 'vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc');
                }])
                ->latest('created_at');

            // Recherche par N° ticket, ID, vendeur ou client
            if (strlen(trim($search)) >= 2) {
                $searchTerm = '%' . trim($search) . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('id', 'like', $searchTerm)
                      ->orWhereHas('vendeur', function ($v) use ($searchTerm) {
                          $v->where('prenom', 'like', $searchTerm)
                            ->orWhere('nom', 'like', $searchTerm);
                      })
                      ->orWhereHas('client', function ($c) use ($searchTerm) {
                          $c->where('prenom', 'like', $searchTerm)
                            ->orWhere('nom', 'like', $searchTerm);
                      });
                });
            }

            $totalAmount = (int) (clone $query)->sum('total');
            $paginator = $query->paginate(10);

            return response()->json([
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_amount' => $totalAmount,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    #la liste de toutes les commandes et  pour un caissier donnees
public function allCommandesByCaissier(Request $request, string $id)
{

    $commandes = Commande::with(['details.produit', 'client', 'vendeur', 'paiements'])
        ->whereDate('created_at', Carbon::parse($request->input('date'))->format('Y-m-d'))
        ->where('caissier_id', $id)
        ->whereIn('statut', ['payee', 'partiellement_payee', 'annulee'])
        ->get();

    $decaissements = \App\Models\Decaissement::with('caissier')
        ->where('caissier_id', $id)
        ->whereDate('created_at', Carbon::parse($request->input('date'))->format('Y-m-d'))
        ->get();

    $historique = collect();

    // 🔵 COMMANDES
    foreach ($commandes as $cmd) {

        $historique->push([
            'id' => $cmd->id,
            'type' => $cmd->statut === 'annulee' ? 'annulation' : 'encaissement',
            'statut' => $cmd->statut,
            'reference' => $cmd->numero,
            'total' => $cmd->total,
            'client' => $cmd->client,
            'vendeur_nom' => $cmd->vendeur?->prenom . ' ' . $cmd->vendeur?->nom,
            'mode_paiement' => $cmd->paiements->first()?->methode_paiement,
            'numero_ticket' => $cmd->numero,
            'description' => null,
            'categorie' => null,
            'created_at' => $cmd->updated_at ?? $cmd->created_at,
        ]);
    }

    // 🔴 DÉCAISSEMENTS
    foreach ($decaissements as $dec) {

        $historique->push([
            'id' => $dec->id,
            'type' => 'decaissement',
            'statut' => $dec->statut,
            'reference' => $dec->libelle,
            'total' => $dec->montant,
            'client' => null,
            'vendeur_nom' => null,
            'mode_paiement' => $dec->methode_paiement,
            'numero_ticket' => null,
            'description' => $dec->motif,
            'categorie' => 'Général',
            'created_at' => $dec->created_at,
        ]);
    }

    return response()->json(
        $historique->sortByDesc('created_at')->values()
    );
}

    #appliquer des filtre par date
    public function getCommandesValidees(Request $request){
        try {
            if(Auth::user()->role == "comptable"){
                $commandes = Commande::query()
                    ->whereIn('statut', ['payee', 'partiellement_payee'])
                    ->with([
                        'details',
                        'client',
                        'vendeur',
                        'paiements' => function($q) {
                            $q->orderBy('date', 'desc');
                        }
                    ])
                    ->latest('created_at');

            } else {
                $commandes = Commande::query()
                    ->whereIn('statut', ['payee', 'partiellement_payee'])
                    ->where('caissier_id', Auth::user()->id)
                    ->with([
                        'details',
                        'client',
                        'vendeur',
                        'paiements' => function($q) {
                            $q->orderBy('date', 'desc');
                        }
                    ])
                    ->latest('created_at');
            }

            // 🔹 Filtres indépendants
            if ($request->filled('type_vente')) {
                $commandes->where('type_vente', $request->input('type_vente'));
            }

            if ($request->filled('type_client')) {
                $commandes->whereHas('client', function($q) use ($request) {
                    $q->where('type_client', $request->input('type_client'));
                });
            }

            // 🔹 Filtre dates
            if ($request->filled('date_debut') && $request->filled('date_fin')) {
                $commandes->whereBetween('date', [$request->date_debut, $request->date_fin]);
            } elseif ($request->filled('date_debut')) {
                $commandes->whereDate('date', '>=', $request->date_debut);
            } elseif ($request->filled('date_fin')) {
                $commandes->whereDate('date', '<=', $request->date_fin);
            }

            return response()->json($commandes->paginate(10));

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes validées',
                'error' => $th->getMessage(),
            ], 500);
        }
  }



    public function getCommandesAnnulees(){
        try {
            return response()->json(Commande::query()
                ->where('statut', 'annulee')
                ->where('created_at','>=',now()->subMonth())
                ->where('caissier_id', Auth::user()->id)
                ->with(['details.produit', 'client', 'vendeur'])
                ->latest('created_at')
                ->paginate(10));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes annulées',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /*
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {

            $validated = $request->validate([
                'client_id' => 'nullable|uuid|exists:clients,id',
                'type_vente' => 'nullable|in:detail,gros,mixte',
                'tva_appliquee' => 'required|boolean',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|uuid|exists:transfert_en_attentes,id',
                'items.*.quantite' => 'required|integer|min:1',
                'items.*.prix_unitaire' => 'nullable|numeric',
                'items.*.mode_vente' => 'required|in:gros,detail', // 🔥 AJOUTER
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la validation des données',
                'error' => $th->getMessage(),
            ], 400);
        }

        try {
                $user = $request->user();
                $tva = $validated['tva_appliquee'] ? 0.18 : 0;
                // 🔥 Détection automatique du type de vente
                $modes = collect($validated['items'])
                    ->pluck('mode_vente')
                    ->unique()
                    ->values();

                if ($modes->count() === 1) {
                    $typeVente = $modes->first(); // "gros" ou "detail"
                } else {
                    $typeVente = 'mixte';
                }

                $commande = Commande::create([
                    'client_id' => $validated['client_id'] ?? null,
                    'vendeur_id' => $user->id,
                    'tva_appliquee' => $validated['tva_appliquee'],
                    'type_vente' => $typeVente,
                    'statut' => 'attente',
                    'total' => 0,
                    'date' => now(),
                ]);

                $lastNumero = Commande::lockForUpdate()->max('numero');
                $next = $lastNumero ? ((int) substr($lastNumero, 4)) + 1 : 1;

                $commande->numero = 'CMD-' . str_pad($next, 6, '0', STR_PAD_LEFT);
                $commande->save();

                $totalHt = 0;

                foreach ($validated['items'] as $item) {
                $transfert = TransfertEnAttente::where('id',$item['id'])
                    ->where('status', 'valide')
                    ->where('quantite', '>', 0)
                    ->orderByDesc('updated_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                # 🔧 Conversion en unités réelles
                if ($item['mode_vente'] === 'gros') {
                    $unitesDemandees = $item['quantite'] * $transfert->produit->unite_carton;
                } else {
                    $unitesDemandees = $item['quantite'];
                }

                if ($unitesDemandees > $transfert->quantite) {
                    throw new \Exception('Stock insuffisant en boutique.');
                }

                    $prix = isset($item['prix_unitaire']) && $item['prix_unitaire'] > 0
                        ? $item['prix_unitaire']
                        : (
                            $item['mode_vente'] === 'gros'
                                ? $transfert->prix_vente_gros
                                : $transfert->prix_vente_detail
                        );

                    $ligneTotal = $prix * $item['quantite'];
                    $totalHt += $ligneTotal;

                    DetailCommande::create([
                        'commande_id' => $commande->id,
                        'transfert_en_attente_id' => $transfert->id,
                        'produit_id' => $transfert->produit_id,
                        'quantite' => $item['quantite'],
                        'prix_unitaire' => $prix,
                        'mode_vente' => $item['mode_vente'], // 🔥 important
                    ]);

                }


                $montantTotal = intval($totalHt+$tva*$totalHt);


                $client = $commande->client;

                if ($client && $client->type_client === 'special') {

                    // Client spécial → paiement par tranche
                    $commande->update([
                        'total_ht' => intval($totalHt),
                        'total_tva' => intval($tva),
                        'total' => $montantTotal,
                        'montant_a_encaisser' => null
                    ]);

                } else {

                    // Client normal → paiement direct
                    $commande->update([
                        'total_ht' => intval($totalHt),
                        'total_tva' => intval($tva),
                        'total' => $montantTotal,
                        'montant_a_encaisser' => $montantTotal
                    ]);

                }
                $commande->load('details', 'vendeur', 'client');

                event(new CommandeValidee($commande));

                return response()->json($commande);

        } catch (\Throwable $th) {

            return response()->json([
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $commande = Commande::with([
                'details',
                'client',
                'vendeur',
                'paiements'
            ])->findOrFail($id);

            $totalPaye = $commande->paiements->sum('montant');

            $commande->montant_paye = $totalPaye;
            $commande->reste_a_payer = max(0, $commande->total - $totalPaye);

            return response()->json($commande);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $commande = Commande::findOrFail($id);
            $data = $request->validate([
                'statut' => 'sometimes|in:attente,partiellement_payee,payee,annulee',
            ]);

            $commande->update($data);

            return $commande->load('details');
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    #la liste des commsndes effectuer par un vendeur donnee
    public function commandesParVendeur(string $id, Request $request)
    {
        try {
            $commandes = Commande::with(['details','client','vendeur'])->where('vendeur_id', $id);
            #filtrer par statut
            if ($request->filled('statut')) {
                $statut = $request->input('statut');
                $commandes->where('statut', $statut);
            }
            #par type de vente
            if ($request->filled('type_vente')) {
                $typeVente = $request->input('type_vente');
                $commandes->where('type_vente', $typeVente);
            }
            #par nom,prenom ,email du client
            if ($request->filled('search')) {
                $search = $request->input('search');
                $commandes->whereHas('client', function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('prenom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            return response()->json($commandes->paginate(10));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $commande = Commande::findOrFail($id);
            if($commande->statut !== 'attente'){
                return response()->json([
                    'message' => 'Seules les commandes en attente peuvent être annulées',
                ], 400);
                abort(400);
            }
            $commande->delete();
            return response()->noContent();
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la suppression de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function valider(string $id)
    {
        try {
            $commande = Commande::findOrFail($id);
            $commande->update(['statut' => 'validee']);
            $commande->load('details', 'vendeur','client');
            event(new CommandeValidee($commande));
            return $commande;
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la validation de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function annuler(string $id)
    {
        $commande = Commande::findOrFail($id);
        if($commande->statut !== 'attente'){
            return response()->json([
                'message' => 'Seules les commandes en attente peuvent être annulées',
            ], 400);
            abort(400);
        }
        $commande->update(['statut' => 'annulee','caissier_id'=>Auth::user()->id]);
        $commande->load('details', 'vendeur', 'client');

        // Diffuser l'événement (sans bloquer si Reverb n'est pas disponible)
        try {
            event(new CommandeValidee($commande));
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'opération
            Log::warning('Erreur lors de la diffusion de l\'annulation: ' . $e->getMessage());
        }
        return $commande;
        try {
            $commande = Commande::findOrFail($id);
            $commande->update(['statut' => 'annulee']);
            $commande->update(['total' => 0]);
            $commande->load('details', 'vendeur','client');
            event(new CommandeValidee($commande));
            return $commande;
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de l\'annulation de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    # les commandes payee aujourduih
    public function commandesPayeesAujourdhui(){
        try {
            $commandes = Commande::where('statut', 'payee')
            ->whereDate('created_at', date('Y-m-d'))
            ->count();
            return response()->json($commandes);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes payées aujourd\'hui',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    #•Montant total des commandes (clients normaux + spéciaux).
    public function montantTotalCommandes(){
        try {
            $montantTotal = Commande::where('statut', '!=', 'annulee')
                ->sum('total');

            return response()->json($montantTotal);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur montant total commandes',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    #Total commandes payées
    public function totalCommandesPayees(){
        try {
            $totalCommandesPayees = Commande::where('statut', 'payee')
            ->count();
            return response()->json($totalCommandesPayees);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du total des commandes payées',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    # o	Commandes en attente caisse
    public function commandesEnAttenteCaisse(){
        try {
            $commandes = Commande::where('statut', 'attente')
            ->count();
            return response()->json($commandes);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes en attente de caisse',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    public function statsCommandesSpeciales(Request $request)
    {
        try {

            $query = Commande::query()
                ->whereHas('client', function ($q) {
                    $q->where('type_client', 'special');
                })
                ;

            // ===============================
            // FILTRES IDENTIQUES AU TABLEAU
            // ===============================

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            if ($request->filled('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('prenom', 'like', "%{$search}%");
                    });
                });
            }

            // ===============================
            // ANNULÉES (compteur séparé)
            // ===============================

            $annulees = (clone $query)
                ->where('statut', 'annulee')
                ->count();

            $statsQuery = clone $query;

            // Actives par défaut
            if (!$request->filled('statut')) {
                $statsQuery->whereIn('statut', [
                    'attente',
                    'partiellement_payee',
                    'payee'
                ]);
            }

            $nb = $statsQuery->count();
            $totalTTC = $statsQuery->sum('total');

            $commandeIds = $statsQuery->pluck('id');

            $totalPaye = DB::table('paiements')
                ->whereIn('commande_id', $commandeIds)
                ->sum('montant');

            $dette = $totalTTC - $totalPaye;

            return response()->json([
                'nb' => $nb,
                'annulees' => $annulees,
                'totalTTC' => $totalTTC,
                'totalPaye' => $totalPaye,
                'dette' => $dette,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur stats commandes spéciales',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
    public function envoyerTranche(Request $request, string $id)
    {
        $request->validate([
            'montant' => 'required|numeric|min:0.01'
        ]);

        $commande = Commande::with('client')->findOrFail($id);
        // 🔒 Empêcher plusieurs tranches en attente
        if ($commande->montant_a_encaisser !== null) {
            return response()->json([
                'message' => 'Une tranche est déjà en attente d’encaissement.'
            ], 400);
        }
        // 🔒 On respecte les statuts backend actuels
        if ($commande->statut !== 'attente' && $commande->statut !== 'partiellement_payee') {
            return response()->json([
                'message' => 'Seules les commandes en attente ou partiellement payées peuvent recevoir une tranche.'
            ], 400);
        }

        // ✅ Vérifier client spécial
        if (!$commande->client || $commande->client->type_client !== 'special') {
            return response()->json([
                'message' => 'Seuls les clients spéciaux peuvent payer par tranche.'
            ], 400);
        }

        $montant = $request->montant;

        // 🔍 Calcul du reste réel
        $totalPaye = $commande->paiements()->sum('montant');
        $reste = $commande->total - $totalPaye;

        if ($montant > $reste) {
            return response()->json([
                'message' => 'Le montant dépasse le reste à payer.'
            ], 400);
        }

        // 🟣 On prépare juste la caisse
        $commande->update([
            'montant_a_encaisser' => $montant
        ]);

        return response()->json([
            'numero' => $commande->numero,
            'montant_a_encaisser' => $montant,
            'reste_avant_paiement' => $reste,
            'message' => 'Tranche envoyée à la caisse.'
        ]);
    }
    public function commandesAvecResteClientSpecial(string $clientId)
    {
        try {

            $commandes = Commande::with(['paiements'])
                ->where('client_id', $clientId)
                ->whereHas('client', function ($q) {
                    $q->where('type_client', 'special');
                })
                ->whereIn('statut', ['attente', 'partiellement_payee'])
                ->get()
                ->map(function ($commande) {

                    $totalPaye = $commande->paiements->sum('montant');

                    $commande->montant_paye = $totalPaye;
                    $commande->reste_a_payer = max(0, $commande->total - $totalPaye);

                    return $commande;
                })
                ->filter(function ($commande) {
                    return $commande->reste_a_payer > 0;
                })
                ->values();

            return response()->json($commandes);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur récupération commandes avec reste',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
