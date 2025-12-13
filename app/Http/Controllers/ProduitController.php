<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Repositories\ProduitRepository;

class ProduitController extends Controller
{
    protected $repository;

    public function __construct(ProduitRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        try {
            return $this->repository->index();
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'code' => 'required|string|unique:produits,code',
            'categorie' => 'nullable|string',
            'prix_vente_detail' => 'required|numeric',
            'prix_vente_gros' => 'nullable|numeric',
            'prix_achat' => 'nullable|numeric',
            'prix_gros' => 'nullable|numeric',
            'prix_seuil_detail' => 'nullable|numeric',
            'prix_seuil_gros' => 'nullable|numeric',
            'quantite' => 'nullable|integer',
            'stock_global' => 'nullable|integer',
        ]);
        $data['stock_global'] = $data['stock_global']*$data['quantite'];
        $produit = Produit::create($data);
        StockBoutique::create([
            'boutique_id' => Auth::user()->boutique_id,
            'produit_id' => $produit->id,
            'quantite' => $produit->stock_global*$produit->quantite,
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
            'prix_vente_detail' => 'sometimes|numeric',
            'prix_vente_gros' => 'nullable|numeric',
            'prix_achat' => 'nullable|numeric',
            'prix_gros' => 'nullable|numeric',
            'prix_seuil_detail' => 'nullable|numeric',
            'prix_seuil_gros' => 'nullable|numeric',
            'quantite' => 'nullable|integer',
            'stock_global' => 'nullable|integer',
        ]);
        $data['stock_global'] = $data['stock_global']*$data['quantite'];
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
