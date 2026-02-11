<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransferRequest;
use App\Models\entree_sortie_boutique;
use App\Models\EntreeSortie;
use Illuminate\Http\Request;
use App\Models\Produit;

class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $transfers = Transfer::with(['produit'])->where('status', 'en_attente')->paginate(10);
            return response()->json($transfers);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    /**
     * Get the total count of products in transfers.
     */
    public function nombreProduits()
    {
        try {
            $count = Transfer::where('status', 'valide')->count();
            return response()->json(['total' => $count]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Get the total quantity of products in transfers.
     */
    public function quantiteTotaleProduit()
    {
        try {
                $totalQuantity = Transfer::where('status', 'valide')->sum('quantite');
                return response()->json(['total_quantity' => $totalQuantity]);
        } catch (\Throwable $th) {
                return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsDisponibles()
    {
        try {
            $transfers = Transfer::with(['produit'])->where('status', 'valide')->paginate(20);
            return response()->json($transfers);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsControleBoutique()
    {
        try {
            $transfers = Transfer::with(['produit'])->where('status', 'valide')->paginate(15);
            $transfers->each(function($transfer) {
                $transfer->produit->etat_stock = $transfer->quantite < $transfer->seuil ? true : false;
                $transfer->produit->entree_sortie = entree_sortie_boutique::where('produit_id', $transfer->produit_id)->get()->first();
            });
            return response()->json($transfers);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsControleDepots()
    {
        try {
            $produits = Produit::with(['entreees_sorties'])->paginate(15);
            $produits->each(function($produit) {
                $produit->etat_stock = $produit->quantite < $produit->stock_seuil ? true : false;
                
            });
            return response()->json($produits);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function produitsByBoutique($boutique_id){
      try {
        $produits = Transfer::where('boutique_id', $boutique_id)
            ->with(['produit'])
            ->get();
        return response()->json($produits);
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


    public function getTransferValide(){
      try {
        $transfers = Transfer::with(['produit'])->where('status', 'valide')->get();
        return response()->json($transfers);
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


    /**
     * Get products below stock threshold
     */
    public function produitsSousSeuil()
    {
        try {
            $transfers = Transfer::with(['produit'])
                ->where('status', 'valide')
                ->whereRaw('quantite <= seuil')
                ->paginate(20);
            return response()->json($transfers);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsRupture()
    {
        try {
            $transfers = Transfer::with(['produit'])
                ->where('status', 'valide')
                ->whereRaw('quantite <= 0')
                ->paginate(20);
            return response()->json($transfers);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function valideTransfer(Request $request){
      try {
            $transfer = Transfer::findOrFail($request->id);
            $transfer->status = 'valide';
            $transfer->seuil = $request->seuil;
            $produit = Produit::where('id', $transfer->produit_id)->get()->first();
            $produit->prix_vente_detail = $request->prix_vente_detail;
            $produit->prix_vente_gros = $request->prix_vente_gros;
            $produit->prix_seuil_detail = $request->prix_seuil_detail;
            $produit->prix_seuil_gros = $request->prix_seuil_gros;
            $produit->save();
            $transfer->save();
            return response()->json($transfer);
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


     public function MontantTotalStock()
    {
        try {
           $total = 0;
           foreach (Transfer::where('status', 'valide')->get() as $transfer) {
            $produit = Produit::where('id', $transfer->produit_id)->get()->first();
            $total += $transfer->quantite * $produit->prix_vente_detail;
           }
           return response()->json(['total' => $total]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTransferRequest $request)
    {

    }

    /**
     * Display the specified resource.
     */
    public function show(Transfer $transfer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransferRequest $request, Transfer $transfer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transfer $transfer)
    {
        //
    }
}
