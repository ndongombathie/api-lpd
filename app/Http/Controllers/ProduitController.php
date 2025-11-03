<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;

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
