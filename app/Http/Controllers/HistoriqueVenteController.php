<?php

namespace App\Http\Controllers;

use App\Models\HistoriqueVente;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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

    public function inventaireBoutique(Request $request)
    {

        $validated = $request->validate([
            'date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $date = $validated['date'] ?? Carbon::now()->format('Y-m-d');

        try {
            // Récupérer les produits vendus à la date donnée avec la quantité totale vendue
            $query = DB::table('historique_ventes')
                ->join('transfers', 'historique_ventes.produit_id', '=', 'transfers.produit_id')
                ->select(
                    'transfers.produit_id',
                    'transfers.quantite as stock_initial',
                    DB::raw('SUM(historique_ventes.quantite) as quantite_vendue')
                )
               // ->whereDate('historique_ventes.created_at', $date)
                ->groupBy('transfers.produit_id', 'transfers.quantite');
            $produitsVendus = $query->paginate(10);
            // Ajouter la colonne écart (stock_initial - quantite_vendue)
            $produitsVendus->getCollection()->transform(function ($produit) {
                $produit->ecart = $produit->stock_initial - $produit->quantite_vendue;
                $produit->produit=Produit::query()->with('entreees_sorties')->where('id',$produit->produit_id)->get()->first();
                $produit->total_vendu=$produit->quantite_vendue*$produit->produit->prix_unite_carton;
                $produit->total_resant=($produit->stock_initial-$produit->quantite_vendue)*$produit->produit->prix_unite_carton > 0 ? ($produit->stock_initial-$produit->quantite_vendue)*$produit->produit->prix_unite_carton  : 0;
                return $produit;
            });

            return response()->json(['date' => $date, 'produits' => $produitsVendus]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

     public function inventaireDepot(Request $request)
    {

        $validated = $request->validate([
            'date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $date = $validated['date'] ?? Carbon::now()->format('Y-m-d');
        $perPage = $validated['per_page'] ?? 50;
        try {
            // Récupérer les produits vendus à la date donnée avec la quantité totale vendue
            $query = DB::table('historique_ventes')
                ->join('transfers', 'historique_ventes.produit_id', '=', 'transfers.produit_id')
                ->select(
                    'transfers.produit_id',
                    'transfers.quantite as stock_initial',
                    DB::raw('SUM(historique_ventes.quantite) as quantite_vendue')
                )
               // ->whereDate('historique_ventes.created_at', $date)
                ->groupBy('transfers.produit_id', 'transfers.quantite');
            $produitsVendus = $query->paginate($perPage);
            // Ajouter la colonne écart (stock_initial - quantite_vendue)
            $produitsVendus->getCollection()->transform(function ($produit) {
                $produit->ecart = $produit->stock_initial - $produit->quantite_vendue;
                $produit->produit=Produit::find($produit->produit_id);
                return $produit;
            });

            return response()->json(['date' => $date, 'produits' => $produitsVendus]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }



    /**
     * Get the total sales for a given day.
     */
    public function totalParJour(Request $request)
    {

        $date = $request->input('date') ?? Carbon::now()->format('Y-m-d');

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
