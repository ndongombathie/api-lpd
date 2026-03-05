<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Events\CommandeValidee;
use App\Events\CommandeAnnulee;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Commande::with('details.produit', 'client', 'vendeur')
               ->orderBy('created_at', 'desc')
               ->where('vendeur_id', Auth::user()->id);

            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
            }
            if (!$request->filled('date')) {
                $query->whereDate('date', now()->toDateString());
            }

            if ($request->filled('status')) {
                $query->where('statut', $request->status);
            }

            if ($request->filled('type')) {
                $query->where('type_vente', $request->type);
            }

            return response()->json($query->paginate(10));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCommandesEnAttente(Request $request){
        try {
            $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
            $page = max((int) $request->input('page', 1), 1);
            $search = $request->input('search', '');

            $query = Commande::query()
                ->where('statut', 'attente')
                # dans le with je veux le dernier paiement de chaque commande
                ->with(['details.produit', 'client', 'vendeur', 'paiements' => function($q){
                    $q->orderByDesc('date')
                        ->limit(1);
                }])
                ->latest();

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
                })->orWhere('numero', 'like', $searchTerm);
            }

            $totalAmount = (int) (clone $query)->sum('total');
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

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
    public function allCommandesByCaissier(Request $request){
            try {
                $query=Commande::query()
                #ajouter les relation details, client, vendeur, paiements
                ->with(['details.produit', 'client', 'vendeur', 'paiements'])
                ->where('caissier_id', Auth::user()->id)
                ->latest();
                #filter par client et numero de commande
                if ($request->filled('search')) {
                    $query->where(function ($q) use ($request) {
                        $q->where('client.nom', 'like', '%'.$request->search.'%')
                          ->orWhere('client.prenom', 'like', '%'.$request->search.'%')
                          ->orWhere('vendeur.nom', 'like', '%'.$request->search.'%')
                          ->orWhere('vendeur.prenom', 'like', '%'.$request->search.'%')
                          ;
                    });
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
    }


    #appliquer des filtre par date
    public function getCommandesValidees(Request $request){
        try {
            if(Auth::user()->role=="comptable"){
                $commandes = Commande::query()
                ->whereIn('statut', ['payee', 'partiellement_payee'])
                ->with(['details','client','vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc'); // Trier les paiements par date décroissante
                }])->latest();

                if ($request->filled('type')) {
                $commandes->where('type_vente', $request->input('type_vente'));
            }
            }else
            {
                $commandes = Commande::query()
                ->whereIn('statut', ['payee', 'partiellement_payee'])
                ->where('caissier_id', Auth::user()->id)
                ->with(['details','client','vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc'); // Trier les paiements par date décroissante
                }])->latest();
            }

            #filtrer entre deux dates date_debut et date_fin
            if ($request->filled('date_debut') && $request->filled('date_fin')) {
                $commandes->whereBetween('date', [$request->date_debut, $request->date_fin]);
            }

            #filtrer par une date donnee
            if ($request->filled('date_debut') || $request->filled('date_fin')) {
                $commandes->whereDate('date', $request->date_debut ?? $request->date_fin);
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
                ->latest()
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
                'type_vente' => 'required|in:detail,gros',
                'tva_appliquee' => 'required|boolean',
                'items' => 'required|array|min:1',
                'items.*.produit_id' => 'required|uuid|exists:produits,id',
                'items.*.quantite' => 'required|integer|min:1',
                'items.*.prix_unitaire' => 'nullable|numeric',
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

                    $commande = Commande::create([
                        'client_id' => $validated['client_id'] ?? null,
                        'vendeur_id' => $user->id,
                        'tva_appliquee' => $validated['tva_appliquee'],
                        'type_vente' => $validated['type_vente'],
                        'statut' => 'attente',
                        'total' => 0,
                        'date' => now(),
                    ]);
                    $lastNumero = Commande::lockForUpdate()->max('numero');
                    $next = $lastNumero
                        ? ((int) substr($lastNumero, 4)) + 1
                        : 1;

                    $commande->numero = 'CMD-' . str_pad($next, 6, '0', STR_PAD_LEFT);

                    $commande->save();

                    $totalHt = 0;
                    foreach ($validated['items'] as $item) {
                        $produit = Produit::findOrFail($item['produit_id']);
                        $prix = $item['prix_unitaire'] ?? ($validated['type_vente'] === 'gros' && $produit->prix_gros ? $produit->prix_gros : $produit->prix_vente);
                        $ligneTotal = $prix * $item['quantite'];
                        $totalHt += $ligneTotal;

                        DetailCommande::create([
                            'commande_id' => $commande->id,
                            'produit_id' => $produit->id,
                            'quantite' => $item['quantite'],
                            'prix_unitaire' => $prix,
                        ]);
                    }

                    $montantTva = $totalHt * $tva;
                    $commande->update(['total' => intval($totalHt + $montantTva)]);

                    if(Auth::user()->role==='rseponsable')
                         $commande->premiere_tranche = 0;
                    else
                        $commande->premiere_tranche = intval($totalHt + $montantTva);
                    
                    $commande->load('details', 'vendeur','client');
                    $commande->save();
                    event(new CommandeValidee($commande));
                    return response()->json($commande);

       }catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

     public function storeTranche(string $commandeId, Request $request)
    {

        try {
                $commande = Commande::findOrFail($commandeId);
                $somme= Paiement::where('commande_id', $commandeId)->sum('montant');

                if($somme >= $commande->total){
                    $commande->statut = 'payee';
                    $commande->premiere_tranche = 0;
                }else{
                    $commande->statut = 'attente';
                    $commande->premiere_tranche = $request->input('montant');
                }
                $commande->save();
                $commande->load('details', 'vendeur','client');
                event(new CommandeValidee($commande));
                return response()->json($commande);
       }catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de la commande',
                'error' => $th->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            return response()->json(Commande::with(['details','client','vendeur'])->findOrFail($id));
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
                'statut' => 'sometimes|in:brouillon,validee,payee,annulee',
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
            event(new CommandeAnnulee($commande));
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
            event(new CommandeAnnulee($commande));
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
                });

            // ===============================
            // FILTRES IDENTIQUES AU FRONT
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
                        $sub->where('nom', 'like', "%{$search}%");
                    });
                });
            }

            // ===============================
            // ANNULÉES (compteur séparé)
            // ===============================

            $annuleesQuery = clone $query;
            $annulees = $annuleesQuery
                ->where('statut', 'annulee')
                ->count();
            $statsQuery = clone $query;

            // Exclure annulées sauf si filtre annulée
            if (!$request->filled('statut') || $request->statut !== 'annulee') {
                $statsQuery->where('statut', '!=', 'annulee');
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
}
