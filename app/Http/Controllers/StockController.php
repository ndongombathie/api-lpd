<?php

namespace App\Http\Controllers;

use App\Models\StockBoutique;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        return StockBoutique::with('produit')->paginate(50);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'source_boutique_id' => 'nullable|uuid',
            'destination_boutique_id' => 'required|uuid',
            'produit_id' => 'required|uuid|exists:produits,id',
            'quantite' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated) {
            $produitId = $validated['produit_id'];
            $qte = $validated['quantite'];

            if (! empty($validated['source_boutique_id'])) {
                $src = StockBoutique::firstOrCreate([
                    'boutique_id' => $validated['source_boutique_id'],
                    'produit_id' => $produitId,
                ]);
                if ($src->quantite < $qte) {
                    abort(422, 'Stock source insuffisant');
                }
                $src->decrement('quantite', $qte);
            } else {
                // depuis dépôt central
                $produit = Produit::findOrFail($produitId);
                if ($produit->stock_global < $qte) {
                    abort(422, 'Stock global insuffisant');
                }
                $produit->decrement('stock_global', $qte);
            }

            $dest = StockBoutique::firstOrCreate([
                'boutique_id' => $validated['destination_boutique_id'],
                'produit_id' => $produitId,
            ]);
            $dest->increment('quantite', $qte);

            return response()->json(['message' => 'Transfert effectué']);
        });
    }
}
