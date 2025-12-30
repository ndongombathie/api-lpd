<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Events\CommandeValidee;
use App\Events\CommandeAnnulee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    public function index()
    {
        try {
            return response()->json( Commande::query()->with('details')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCommandesEnAttente(){
        try {
            return response()->json(Commande::query()->where('statut', 'attente')->with('details','client')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCommandesValidees(){
        try {
            return response()->json(Commande::query()->where('statut', 'payee')->with('details','client','vendeur')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes validées',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getCommandesAnnulees(){
        try {
            return response()->json(Commande::query()->where('statut', 'annulee')->where('created_at','>=',now()->subMonth())->with('details','client','vendeur')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des commandes annulées',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|uuid|exists:clients,id',
            'type_vente' => 'required|in:detail,gros',
            'tva' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.produit_id' => 'required|uuid|exists:produits,id',
            'items.*.quantite' => 'required|integer|min:1',
            'items.*.prix_unitaire' => 'nullable|numeric',
        ]);

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
            return $commande;
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Commande::with(['details','client'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $commande = Commande::findOrFail($id);
        $data = $request->validate([
            'statut' => 'sometimes|in:brouillon,validee,payee,annulee',
        ]);
        $commande->update($data);
        return $commande->load('details');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->delete();
        return response()->noContent();
    }

    public function valider(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->update(['statut' => 'validee']);
        $commande->load('details', 'vendeur');
        event(new CommandeValidee($commande));
        return $commande;
    }

    public function annuler(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->update(['statut' => 'annulee']);
        $commande->load('details', 'vendeur');
        event(new CommandeAnnulee($commande));
        return $commande;
    }
}
