<?php

namespace App\Http\Controllers;

use App\Models\StockBoutique;
use App\Models\Produit;
use App\Models\MouvementStock;
use App\Events\StockBoutiqueMisAJour;
use App\Events\StockRupture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        return StockBoutique::with('produit')->paginate(50);
    }

    public function ruptures(Request $request)
    {
        $boutiqueId = $request->query('boutique_id');
        return StockBoutique::with('produit')
            ->where('quantite', '<=', 10)
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->paginate(50);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'source_boutique_id' => 'nullable|uuid|exists:boutiques,id',
            'destination_boutique_id' => 'required|uuid|exists:boutiques,id',
            'produit_id' => 'required|uuid|exists:produits,id',
            'quantite' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $produitId = $validated['produit_id'];
            $qte = $validated['quantite'];
            $sourceLabel = 'depot';

            if (! empty($validated['source_boutique_id'])) {
                $src = StockBoutique::firstOrCreate([
                    'boutique_id' => $validated['source_boutique_id'],
                    'produit_id' => $produitId,
                ]);
                if ($src->quantite < $qte) {
                    abort(422, 'Stock source insuffisant');
                }
                $src->decrement('quantite', $qte);
                $sourceLabel = 'boutique:' . $validated['source_boutique_id'];

                MouvementStock::create([
                    'source' => $sourceLabel,
                    'destination' => 'boutique:' . $validated['destination_boutique_id'],
                    'produit_id' => $produitId,
                    'quantite' => $qte,
                    'type' => 'sortie',
                    'date' => now(),
                ]);

                if ($src->quantite <= 0) {
                    event(new StockRupture($src->fresh()));
                }
            } else {
                // depuis dépôt central
                $produit = Produit::findOrFail($produitId);
                if ($produit->stock_global < $qte) {
                    abort(422, 'Stock global insuffisant');
                }
                $produit->decrement('stock_global', $qte);

                MouvementStock::create([
                    'source' => $sourceLabel,
                    'destination' => 'boutique:' . $validated['destination_boutique_id'],
                    'produit_id' => $produitId,
                    'quantite' => $qte,
                    'type' => 'sortie',
                    'date' => now(),
                ]);
            }

            $dest = StockBoutique::firstOrCreate([
                'boutique_id' => $validated['destination_boutique_id'],
                'produit_id' => $produitId,
            ]);
            $dest->increment('quantite', $qte);

            MouvementStock::create([
                'source' => $sourceLabel,
                'destination' => 'boutique:' . $validated['destination_boutique_id'],
                'produit_id' => $produitId,
                'quantite' => $qte,
                'type' => 'entree',
                'date' => now(),
            ]);

            // Diffuser la mise à jour de stock vers la boutique de destination
            event(new StockBoutiqueMisAJour($dest->fresh()));

            return response()->json(['message' => 'Transfert effectué']);
        });
    }

    /**
     * Réapprovisionner une boutique depuis le dépôt.
     * Deux modes:
     * - fournir explicitement 'quantite'
     * - ou fixer un 'stock_cible' pour remplir jusqu'à ce seuil
     */
    public function reapprovisionner(Request $request)
    {
        $validated = $request->validate([
            'destination_boutique_id' => 'required|uuid|exists:boutiques,id',
            'produit_id' => 'required|uuid|exists:produits,id',
            'quantite' => 'nullable|integer|min:1',
            'stock_cible' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['quantite']) && empty($validated['stock_cible'])) {
            abort(422, 'Spécifiez soit quantite soit stock_cible');
        }

        return DB::transaction(function () use ($validated) {
            $produit = Produit::findOrFail($validated['produit_id']);
            $dest = StockBoutique::firstOrCreate([
                'boutique_id' => $validated['destination_boutique_id'],
                'produit_id' => $validated['produit_id'],
            ]);

            $qte = $validated['quantite'] ?? null;
            if ($qte === null) {
                $current = $dest->quantite;
                $target = $validated['stock_cible'];
                $qte = max(0, $target - $current);
                if ($qte <= 0) {
                    return response()->json([
                        'message' => 'Stock déjà supérieur ou égal au stock cible',
                        'quantite' => 0,
                    ], 200);
                }
            }

            if ($produit->stock_global < $qte) {
                abort(422, 'Stock global insuffisant');
            }

            // Sortie du dépôt
            $produit->decrement('stock_global', $qte);
            MouvementStock::create([
                'source' => 'depot',
                'destination' => 'boutique:' . $validated['destination_boutique_id'],
                'produit_id' => $validated['produit_id'],
                'quantite' => $qte,
                'type' => 'sortie',
                'date' => now(),
            ]);

            // Entrée en boutique
            $dest->increment('quantite', $qte);
            MouvementStock::create([
                'source' => 'depot',
                'destination' => 'boutique:' . $validated['destination_boutique_id'],
                'produit_id' => $validated['produit_id'],
                'quantite' => $qte,
                'type' => 'entree',
                'date' => now(),
            ]);

            event(new StockBoutiqueMisAJour($dest->fresh()));

            return response()->json([
                'message' => 'Réapprovisionnement effectué',
                'quantite' => $qte,
            ]);
        });
    }
}
