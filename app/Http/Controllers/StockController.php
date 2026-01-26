<?php

namespace App\Http\Controllers;

use App\Models\StockBoutique;
use App\Models\Produit;
use App\Models\MouvementStock;
use App\Events\StockBoutiqueMisAJour;
use App\Events\StockRupture;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        try {
            return StockBoutique::with('produit')->paginate(50);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function ruptures(Request $request)
    {
        $boutiqueId = $request->query('boutique_id');
        return StockBoutique::with('produit')
            ->where('quantite', '<=', 'stock_seuil')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->paginate(50);
    }

    public function transfer(Request $request)
    {
        //dd($request->all());
       try {
        $validated = $request->validate([
            'produit_id' => 'required|uuid|exists:produits,id',
            'quantite' => 'required|integer|min:1',
        ]);
            $produitId = $validated['produit_id'];
            $qte = $validated['quantite'];
            $sourceLabel = 'depot';

            if (!empty(Auth::user()->boutique_id)) {
                $src = StockBoutique::firstOrCreate([
                    'boutique_id' => Auth::user()->boutique_id,
                    'produit_id' => $produitId,
                ]);
                $transfer = Transfer::firstOrCreate([
                    'boutique_id' => Auth::user()->boutique_id,
                    'produit_id'  => $produitId,
                ], [
                    'quantite' => 0, // provide a default value for the NOT NULL column
                ]);
                $produit = Produit::findOrFail($produitId);
                $produit->decrement('nombre_carton', $qte);
                $produit->decrement('stock_disponible', $qte*$produit->unite_carton);
                $produit->save();

                $transfer->increment('quantite', $qte*$produit->unite_carton);
                $transfer->increment('quantite', $qte);
                $transfer->updated_at = now();
                $transfer->save();

                if ($src->quantite < $qte) {
                    abort(422, 'Stock source insuffisant');
                }

                $src->decrement('quantite', $qte);


                $sourceLabel = 'boutique:' . Auth::user()->boutique_id;
                MouvementStock::create([
                    'source' => $sourceLabel,
                    'destination' => 'boutique:' . Auth::user()->boutique_id,
                    'produit_id' => $produitId,
                    'quantite' => $qte,
                    'type' => 'sortie',
                    'date' => now(),
                ]);

                if ($src->quantite <= 0) {
                    event(new StockRupture($src->fresh()));
                }
            }
            return response()->json(['message' => 'Transfert effectué']);
        }
        catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

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
            'produit_id' => 'required|uuid|exists:produits,id',
            'quantite' => 'nullable|integer|min:1',
        ]);

        if (empty($validated['quantite']) && empty($validated['stock_cible'])) {
            abort(422, 'Spécifiez soit quantite soit stock_cible');
        }

        return DB::transaction(function () use ($validated) {
            $produit = Produit::findOrFail($validated['produit_id']);
            $dest = StockBoutique::firstOrCreate([
                'boutique_id' => Auth::user()->boutique_id,
                'produit_id' => $validated['produit_id'],
            ]);

            $qte = $validated['quantite'] ?? null;
            if ($qte !== null) {
              $dest->increment('quantite', $qte);
              $produit->increment('stock_global', $qte*$produit->unite_carton);
            }

            if ($produit->stock_global < $qte) {
                abort(422, 'Stock global insuffisant');
            }


            // Entrée en boutique
            $dest->increment('quantite', $qte);
            MouvementStock::create([
                'source' => 'depot',
                'destination' => 'boutique:' . Auth::user()->boutique_id,
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
