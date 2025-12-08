<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProduitController extends Controller
{
    /**
     * GET /api/produits
     * Liste paginée + recherche simple
     */
    public function index(Request $request)
    {
        $query = Produit::query();

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('categorie', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20);
    }

    /**
     * GET /api/produits/catalogue
     * Version light pour les écrans (Commandes, etc.)
     */
    public function catalogue()
    {
        $items = Produit::orderBy('nom')->get()->map(function (Produit $p) {
            return [
                'id' => $p->id,
                'ref' => $p->code, // code-barres → utilisé comme "réf" dans le front
                'libelle' => $p->categorie
                    ? "{$p->nom} ({$p->categorie})"
                    : $p->nom,
                'prix_detail' => $p->prix_basique_detail,
                'prix_gros' => $p->prix_basique_gros,
                'stock_global' => $p->stock_global,
            ];
        });

        return response()->json($items);
    }

    /**
     * POST /api/produits
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:produits,code'],
            'categorie' => ['nullable', 'string', 'max:255'],

            'nombre_cartons' => ['required', 'integer', 'min:0'],
            'unites_par_carton' => ['required', 'integer', 'min:0'],
            'quantite_seuil' => ['required', 'integer', 'min:0'],

            'prix_basique_detail' => ['nullable', 'integer', 'min:0'],
            'prix_seuil_detail' => ['nullable', 'integer', 'min:0', 'lte:prix_basique_detail'],

            'prix_basique_gros' => ['nullable', 'integer', 'min:0'],
            'prix_seuil_gros' => ['nullable', 'integer', 'min:0', 'lte:prix_basique_gros'],

            'cout_acquisition_total' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['cout_acquisition_total'] = $data['cout_acquisition_total'] ?? 0;

        $produit = Produit::create($data);

        return response()->json($produit, 201);
    }

    /**
     * GET /api/produits/{id}
     */
    public function show(string $id)
    {
        $produit = Produit::findOrFail($id);
        return response()->json($produit);
    }

    /**
     * PUT /api/produits/{id}
     */
    public function update(Request $request, string $id)
    {
        $produit = Produit::findOrFail($id);

        $data = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('produits', 'code')->ignore($produit->id, 'id'),
            ],
            'categorie' => ['nullable', 'string', 'max:255'],

            'nombre_cartons' => ['sometimes', 'integer', 'min:0'],
            'unites_par_carton' => ['sometimes', 'integer', 'min:0'],
            'quantite_seuil' => ['sometimes', 'integer', 'min:0'],

            'prix_basique_detail' => ['nullable', 'integer', 'min:0'],
            'prix_seuil_detail' => ['nullable', 'integer', 'min:0', 'lte:prix_basique_detail'],

            'prix_basique_gros' => ['nullable', 'integer', 'min:0'],
            'prix_seuil_gros' => ['nullable', 'integer', 'min:0', 'lte:prix_basique_gros'],

            'cout_acquisition_total' => ['nullable', 'integer', 'min:0'],
        ]);

        $produit->update($data);

        return response()->json($produit);
    }

    /**
     * DELETE /api/produits/{id}
     */
    public function destroy(string $id)
    {
        $produit = Produit::findOrFail($id);
        $produit->delete();

        return response()->noContent();
    }
}
