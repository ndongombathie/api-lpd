<?php

namespace App\Http\Controllers;

use App\Models\HistoriqueVente;
use Illuminate\Http\Request;

class HistoriqueVenteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $historiqueVentes = HistoriqueVente::with(['vendeur', 'produit'])->paginate(10);
            return response()->json($historiqueVentes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
    public function show(HistoriqueVente $historiqueVente)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, HistoriqueVente $historiqueVente)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HistoriqueVente $historiqueVente)
    {
        //
    }
}
