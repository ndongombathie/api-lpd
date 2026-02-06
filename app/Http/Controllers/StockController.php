<?php

namespace App\Http\Controllers;

use App\Models\StockBoutique;
use App\Models\Produit;
use App\Models\MouvementStock;
use App\Events\StockBoutiqueMisAJour;
use App\Events\StockRupture;
use App\Models\entree_sortie_boutique;
use App\Models\EntreeSortie;
use App\Models\HistoriqueAction;
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
            ->paginate(20);
    }

    public function transfer(Request $request)
    {
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
                    'nombre_carton' => 0, // provide a default value for the NOT NULL column
                ]);

                if ($src->nombre_carton < $qte) {
                    abort(422, 'Stock source insuffisant');
                }

                $produit = Produit::findOrFail($produitId);
                $produit->decrement('nombre_carton', $qte);
                $produit->decrement('stock_global', $qte*$produit->unite_carton);
                $produit->save();

                $transfer->increment('quantite', $qte*$produit->unite_carton);
                $transfer->increment('nombre_carton', $qte);
                $transfer->updated_at = now();
                $transfer->save();

                $this->EntreeSortiesBoutique($produitId,$qte);
                $this->Sorties($produitId,$qte);
                $src->decrement('quantite', $qte);
                $sourceLabel = 'boutique:' . Auth::user()->boutique_id;

                MouvementStock::firstOrCreate([
                    'source' => $sourceLabel,
                    'destination' => 'boutique:' . Auth::user()->boutique_id,
                    'produit_id' => $produitId,
                    'quantite' => $qte,
                    'type' => 'sortie',
                    'motif' => 'Transfert de produit',
                ],[
                     'date' => now(),
                ]);

                HistoriqueAction::create([
                    'user_id' => Auth::user()->id,
                    'produit_id' => $produitId,
                    'action' => 'Transfert de produit',
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

    public function EntreeSorties($produitId,$qte)
    {
        $entree_sortie=EntreeSortie::firstOrCreate([
            'produit_id'  => $produitId,
        ], [
            'quantite_avant' => 0,
            'quantite_apres' => 0,
            'nombre_fois'=>0
        ]);
        $entree_sortie->quantite_avant=$entree_sortie->quantite_apres;
        $entree_sortie->increment('quantite_apres',$qte);
        $entree_sortie->increment('nombre_fois',1);
        $entree_sortie->save();

    }

    public function Sorties($produitId,$qte)
    {
        $entree_sortie=EntreeSortie::firstOrCreate([
            'produit_id'  => $produitId,
        ], [
            'quantite_avant' => 0,
            'quantite_apres' => 0,
            'nombre_fois'=>0
        ]);
        $entree_sortie->quantite_avant=$entree_sortie->quantite_apres;
        $entree_sortie->decrement('quantite_apres',$qte);
        $entree_sortie->save();

    }

    public function EntreeSortiesBoutique($produitId,$qte)
    {
        $entree_sortie=entree_sortie_boutique::firstOrCreate([
            'produit_id'  => $produitId,
        ], [
            'quantite_avant' => 0,
            'quantite_apres' => 0,
            'nombre_fois'=>0
        ]);
        $entree_sortie->quantite_avant=$entree_sortie->quantite_apres;
        $entree_sortie->increment('quantite_apres',$qte);
        $entree_sortie->increment('nombre_fois',1);
        $entree_sortie->save();

    }

    /**
     * Réapprovisionner une boutique depuis le dépôt.
     * Deux modes:
     * - fournir explicitement 'quantite'
     * - ou fixer un 'stock_cible' pour remplir jusqu'à ce seuil
     */
    public function reapprovisionner(Request $request)
    {

        try {
                $validated = $request->validate([
                    'produit_id' => 'required|uuid|exists:produits,id',
                    'quantite' => 'nullable|integer|min:1',
                ]);

                if (empty($validated['quantite'])) {
                    abort(422, 'Spécifiez soit quantite soit stock_cible');
                }

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

                    MouvementStock::firstOrCreate([
                        'source' => 'depot',
                        'destination' => 'boutique:' . Auth::user()->boutique_id,
                        'produit_id' => $validated['produit_id'],
                        'quantite' => $qte,
                        'type' => 'entree',
                        'motif' => 'Approvisionnement de produit',
                    ],[
                        'date' => now(),
                    ]);

                    //create historique action
                    HistoriqueAction::create([
                        'user_id' => Auth::user()->id,
                        'produit_id' => $validated['produit_id'],
                        'action' => 'Approvisionnement de produit',
                    ]);

                    $this->EntreeSorties($validated['produit_id'],$validated['quantite']);
                    event(new StockBoutiqueMisAJour($dest->fresh()));
                    return response()->json([
                        'message' => 'Réapprovisionnement effectué',
                        'quantite' => $qte,
                    ]);

                } catch (\Throwable $th) {
                //throw $th;
            }
    }
}
