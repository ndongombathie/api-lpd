<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProduitController extends Controller
{
    public function index()
    {
        return Produit::query()->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'code' => 'required|string|unique:produits,code',
            'categorie' => 'nullable|string',
            'prix_vente' => 'required|numeric',
            'prix_gros' => 'nullable|numeric',
            'prix_seuil' => 'nullable|numeric',
            'stock_global' => 'nullable|integer',
        ]);
        $produit = Produit::create($data);
        StockBoutique::create([
            'boutique_id' => Auth::user()->boutique_id,
            'produit_id' => $produit->id,
            'quantite' => $produit->stock_global,
        ]);
        return response()->json($produit, 201);
    }

    public function show(string $id)
    {
        $produit = Produit::findOrFail($id);
        return $produit;
    }

    public function update(Request $request, string $id)
    {
        $produit = Produit::findOrFail($id);
        $data = $request->validate([
            'nom' => 'sometimes|string',
            'code' => 'sometimes|string|unique:produits,code,' . $produit->id . ',id',
            'categorie' => 'nullable|string',
            'prix_vente' => 'sometimes|numeric',
            'prix_gros' => 'nullable|numeric',
            'prix_seuil' => 'nullable|numeric',
            'stock_global' => 'nullable|integer',
        ]);
        $produit->update($data);
        return $produit;
    }

    public function destroy(string $id)
    {
        $produit = Produit::findOrFail($id);
        $produit->delete();
        return response()->noContent();
    }
}
