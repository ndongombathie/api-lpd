<?php

namespace App\Http\Controllers;

use App\Models\EntreeSortie;
use App\Models\MouvementStock;
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

    public function index(Request $request)
    {
        try {

            $query = Produit::query();

            // =========================
            // 🔎 RECHERCHE PRODUIT
            // =========================
            if ($request->filled('search')) {

                $s = trim($request->search);

                $query->where(function ($q) use ($s) {

                    // =========================
                    // ✅ SCAN CODE-BARRES (PRIORITÉ)
                    // =========================
                    if (is_numeric($s)) {

                        $q->where('code', $s)
                        ->orWhere('code', 'like', "%$s%")
                        ->orWhere('nom', 'like', "%$s%");

                    } else {

                        // =========================
                        // ✅ RECHERCHE TEXTE
                        // =========================
                        $q->where('nom', 'like', "%$s%")
                        ->orWhere('code', 'like', "%$s%");
                    }
                });
            }



            return $query
                ->orderBy('nom')
                ->paginate(50);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }


    public function produits_en_rupture()
    {
        try {
            return Produit::whereColumn('nombre_carton', '<=', 'stock_seuil')->paginate(50);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
            'nom' => 'required|string',
            'code' => 'required|string|unique:produits,code',
            'categorie' => 'nullable|string',
            'unite_carton' => 'nullable|string',
            'prix_unite_carton' => 'nullable|numeric',
            'nombre_carton' => 'nullable|integer',
            'stock_seuil' => 'nullable|integer',
            ]);
            $data['stock_global'] = $data['unite_carton']*$data['nombre_carton'];
            $data['prix_total'] = $data['prix_unite_carton']*($data['nombre_carton']*$data['unite_carton']);
            $produit = Produit::create($data);
            StockBoutique::create([
                'boutique_id' => Auth::user()->boutique_id,
                'produit_id' => $produit->id,
                'nombre_carton' => $produit->nombre_carton,
                'quantite' => $produit->unite_carton*$produit->nombre_carton,
            ]);
            MouvementStock::firstOrCreate([
                            'source' => 'depot',
                            'destination' => 'boutique:' . Auth::user()->boutique_id,
                            'produit_id' => $produit->id,
                            'quantite' => $produit->nombre_carton,
                            'type' => 'Entree',
                        ],[
                            'date' => now(),
                        ]);
            $this->EntreeSorties($produit->id,$produit->nombre_carton);
            return response()->json($produit, 201);
        }
        catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function EntreeSorties($produitId,$qte)
    {
        $entree_sortie=EntreeSortie::firstOrCreate([
            'produit_id'  => $produitId,
        ], [
            'quantite_avant' => 0,
            'quantite_apres' => 0,
            'nombre_fois'=>0
        ]);
        $entree_sortie->quantite_avant=$entree_sortie->quantite_apres;
        $entree_sortie->increment('quantite_apres',$qte);
        $entree_sortie->increment('nombre_fois',1);
        $entree_sortie->save();

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
            'nom' => 'required|string',
            'code' => 'required|string|unique:produits,code',
            'categorie_id' => 'nullable|string',
            'unite_carton' => 'nullable|string',
            'prix_unite_carton' => 'nullable|numeric',
            'nombre_carton' => 'nullable|integer',
            'stock_seuil' => 'nullable|integer',
        ]);
        $data['stock_global'] = $data['unite_carton']*$data['nombre_carton'];
        $data['prix_total'] = $data['prix_unite_carton']*($data['nombre_carton']*$data['unite_carton']);
        $produit->update($data);
        return response()->json($produit);
    }



    public function destroy(string $id)
    {
        $produit = Produit::findOrFail($id);
        $produit->delete();
        return response()->noContent();
    }
}
