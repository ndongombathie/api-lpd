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
            // Ordonner par ordre croissant par heure d'arrivée (created_at)
            $historiqueVentes = HistoriqueVente::with(['vendeur', 'produit'])
                ->orderBy('created_at', 'asc')
                ->paginate(10);
            return response()->json($historiqueVentes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the total sales for a given day.
     */
    public function totalParJour(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date = $request->input('date');

        try {
            $total = HistoriqueVente::whereDate('created_at', $date)->sum('montant');
            return response()->json(['date' => $date, 'total' => $total]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Store a newly created resource in storage.
     */


    public function store(Request $request)
    {
        try {
            $request->validate([
                'vendeur_id' => 'required|exists:users,id',
                'produit_id' => 'required|exists:produits,id',
                'quantite' => 'required|integer|min:1',
                'montant' => 'required|numeric|min:0',
            ]);

            $historiqueVente = HistoriqueVente::create($request->all());
            return response()->json($historiqueVente, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
