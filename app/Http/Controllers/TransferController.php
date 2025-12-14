<?php

namespace App\Http\Controllers;

use App\Models\Transfer;
use App\Http\Requests\StoreTransferRequest;
use App\Http\Requests\UpdateTransferRequest;
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
            $transfers = Transfer::with(['produit', 'boutique'])->get();
            return response()->json($transfers);
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
        $transfers = Transfer::with(['produit', 'boutique'])->where('status', 'valide')->get();
        return response()->json($transfers);
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


    public function valideTransfer(Request $request){
      try {
        $transfer = Transfer::findOrFail($request->id);
        $transfer->update(['status' => 'valide']);
        $produit = Produit::where('id', $transfer->produit_id)->get()->first();
        $produit->update([
          'prix_vente_detail' => $request->prix_unitaire,
          'prix_vente_gros' => $request->prix_gros,
          'prix_seuil_detail' => $request->prix_seuil_detail,
          'prix_seuil_gros' => $request->prix_seuil_gros,
        ]);
        $produit->save();
        return response()->json($transfer);
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
