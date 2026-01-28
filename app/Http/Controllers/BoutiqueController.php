<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use App\Models\Produit;
use Illuminate\Http\Request;

class BoutiqueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function montantTotalBoutique(){
        try {
            $montantTotalDecaissement =Decaissement::sum('montant');
            $montantTotalProduit =Produit::sum('prix_unite_carton');
            $montantTotal = $montantTotalProduit - $montantTotalDecaissement;
            return response()->json(['montant_total' => $montantTotal], 200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    public function BeneficeBoutique(){
        try {
            $benefice = $this->montantTotalBoutique()['montant_total'] - Produit::sum('prix_unite_carton');
            return response()->json(['benefice' => $benefice], 200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
