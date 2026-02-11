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

class CommandeController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Commande::with('details', 'client', 'vendeur')
                ->where('vendeur_id', Auth::user()->id);

            if ($request->filled('date')) {
                $query->whereDate('date', $request->date);
            }

            if ($request->filled('status')) {
                $query->where('statut', $request->status);
            }

            if ($request->filled('type')) {
                $query->where('type_vente', $request->type);
            }

            return response()->json($query->paginate(20));
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
                ->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCommandesValidees(){
        try {
            return response()->json(Commande::query()
                ->where('statut', 'payee')
                ->with(['details','client','vendeur', 'paiements' => function($q) {
                    $q->orderBy('date', 'desc'); // Trier les paiements par date décroissante
                }])
                ->latest()
                ->paginate(20));
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
                ->with(['details.produit', 'client', 'vendeur'])
                ->latest()
                ->paginate(20));
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
                'tva' => 'nullable|numeric',
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
                $tva = $validated['tva'] ?? 0.18; // 18% par défaut

                return DB::transaction(function () use ($validated, $user, $tva) {
                    $commande = Commande::create([
                        'client_id' => $validated['client_id'] ?? null,
                        'vendeur_id' => $user->id,
                        'type_vente' => $validated['type_vente'],
                        'statut' => 'attente',
                        'total' => 0,
                        'date' => now(),
                    ]);

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
                    $commande->update(['total' => $totalHt + $montantTva]);
                    $commande->load('details', 'vendeur','client');
                    event(new CommandeValidee($commande));
                    return response()->json($commande);
                });

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
        $commande->update(['statut' => 'annulee']);
        $commande->load('details', 'vendeur', 'client');

        // Diffuser l'événement (sans bloquer si Reverb n'est pas disponible)
        try {
            event(new CommandeAnnulee($commande));
        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas l'opération
            \Log::warning('Erreur lors de la diffusion de l\'annulation: ' . $e->getMessage());
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
}
