<?php

namespace App\Http\Controllers;

use App\Models\StockBoutique;
use App\Models\Produit;
use App\Models\MouvementStock;
use App\Events\StockBoutiqueMisAJour;
use App\Events\StockRupture;
use App\Models\entree_sortie_boutique;
use App\Models\EntreeSortie;
use App\Models\EntreeSortieBoutique;
use App\Models\HistoriqueAction;
use App\Models\Transfer;
use App\Models\TransfertEnAttente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        try {
            return StockBoutique::with('produit')
            ->orderBy('created_at', 'desc')
            ->paginate(50);
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
            ->paginate(10);
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

                if ($src->nombre_carton < $qte) {
                    abort(422, 'Stock source insuffisant');
                }

                $produit = Produit::findOrFail($produitId);
                $produit->decrement('nombre_carton', $qte);
                $produit->decrement('stock_global', $qte*$produit->unite_carton);
                $produit->save();

                $transfer = TransfertEnAttente::Create([
                        'produit_id'  => $produitId,
                        'quantite' => $qte*$produit->unite_carton, // provide a default value for the NOT NULL column
                        'nombre_carton' => $qte,
                        'quantite_initial' => $qte*$produit->unite_carton,
                    ]);

                $this->EntreeSortiesBoutique($produitId,$qte);
                $this->Sorties($produitId,$qte);

                $src->decrement('quantite', $qte*$produit->unite_carton);
                $src->decrement('nombre_carton',$qte);
                $src->transfert_en_attente_id = $transfer->id;
                $src->save();

                $sourceLabel = 'boutique:' . Auth::user()->boutique_id;

                MouvementStock::firstOrCreate([
                    'source' => $sourceLabel,
                    'destination' => 'Boutique',
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

                event(new StockRupture($produit,Auth::user()->boutique_id));
            }

            return response()->json(['message' => 'Transfert effectué']);
        }
        catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

    }

    /**
     * Annuler le dernier transfert d’un produit depuis la boutique vers le dépôt un en dans paramatre id du transfert.
     * Le transfert est identifié via le modèle Transfer (boutique + produit).
     * Toutes les quantités déplacées sont remises à leur état d’origine.
     */
    public function annulerTransfert(Request $request)
    {
        try {
            $validated = $request->validate([
                'transfer_id' => 'required|uuid|exists:transfert_en_attentes,id',
            ]);
            $id = $validated['transfer_id'];

            $transfer = TransfertEnAttente::findOrFail($id);
            if ($transfer->status != 'en_attente') {
                abort(422, 'Transfert non en attente');
            }

            $transfer->status = 'annuler';
            $transfer->updated_at = now();
            $transfer->save();

            $this->EntreeSorties($transfer->produit_id,$transfer->nombre_carton);
            $this->EntreeSortiesBoutique($transfer->produit_id,$transfer->nombre_carton);
            $produit = Produit::findOrFail($transfer->produit_id);
            $boutiqueId = $transfer->boutique_id;

            // Restaurer le stock boutique
            $src = StockBoutique::firstOrCreate([
                'boutique_id' => Auth::user()->boutique_id,
                'produit_id'  => $transfer->produit_id,
            ]);
            $src->increment('quantite', $transfer->quantite);
            $src->increment('nombre_carton', $transfer->nombre_carton);

            // Restaurer le stock global et nombre_carton du produit
            $produit->increment('stock_global', $transfer->quantite);
            $produit->increment('nombre_carton', $transfer->nombre_carton);

            // Créer le mouvement de retour
            MouvementStock::firstOrCreate([
                'source'      => 'boutique:' . $boutiqueId,
                'destination' => 'depot',
                'produit_id'  => $transfer->produit_id,
                'quantite'    => $transfer->quantite,
                'type'        => 'entree',
                'motif'       => 'Annulation de transfert',
            ], [
                'date' => now(),
            ]);

            // Historique
            HistoriqueAction::create([
                'user_id'    => Auth::user()->id,
                'produit_id' => $transfer->produit_id,
                'action'     => 'Annulation de transfert',
            ]);

            ///Supprimer le transfert
            $src->save();
            return response()->json(['message' => 'Transfert annulé']);
        } catch (\Exception $e) {
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
        $entree_sortie=EntreeSortieBoutique::firstOrCreate([
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
                    $produit->increment('nombre_carton',$qte);
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
                    return response()->json([
                        'message' => 'Réapprovisionnement effectué',
                        'quantite' => $qte,
                    ]);

                } catch (\Throwable $th) {
                //throw $th;
            }
    }
}
