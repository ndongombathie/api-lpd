<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Events\CommandeValidee;
use App\Events\CommandeAnnulee;
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
               ->orderBy('created_at', 'desc');
               //->where('vendeur_id', Auth::user()->id);

            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
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

    public function getCommandesEnAttente(){
        try {
            return response()->json(Commande::query()
                ->where('statut', 'attente')
                ->with(['details.produit', 'client', 'vendeur', 'paiements'])
                ->latest()
                ->paginate(10));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    #appliquer des filtre par date
    public function getCommandesValidees(Request $request){
        try {
            if(Auth::user()->role=="comptable"){
                $commandes = Commande::query()
                ->where('statut', 'payee')
                ->with(['details','client','vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc'); // Trier les paiements par date décroissante
                }]);
            }else
            {
                $commandes = Commande::query()
                ->where('statut', 'payee')
                ->where('caissier_id', Auth::user()->id)
                ->with(['details','client','vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc'); // Trier les paiements par date décroissante
                }])
                ->latest();
            }

            if ($request->filled('date')) {
                $commandes->whereDate('date', $request->date);
            }

            if ($request->filled('type')) {
                $commandes->where('type_vente', $request->type);
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
            $montantTotal = Commande::where('statut', 'payee')
            ->sum('montant_total');
            return response()->json($montantTotal);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du montant total des commandes',
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
}
