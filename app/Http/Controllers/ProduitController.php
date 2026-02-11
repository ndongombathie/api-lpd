<?php

namespace App\Http\Controllers;

use App\Models\EntreeSortie;
use App\Models\Fournisseur;
use App\Models\HistoriqueAction;
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

    public function index()
    {
        try {
            return $this->repository->index();
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
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
            'categorie_id' => 'nullable|string',
            'fournisseur_id' => 'nullable|string',
            'unite_carton' => 'nullable|integer',
            'prix_unite_carton' => 'nullable|numeric',
            'nombre_carton' => 'nullable|integer',
            'stock_seuil' => 'nullable|integer',
            ]);

            $data['stock_global'] = $data['unite_carton']*$data['nombre_carton'];
            $data['prix_total'] = $data['prix_unite_carton']*($data['nombre_carton']*$data['unite_carton']);
            //dd($data);
            $produit = Produit::create($data);

            $fournisseur = Fournisseur::findOrFail($data['fournisseur_id']);
            $fournisseur->increment('total_achats',$produit->prix_total);
            $fournisseur->date_dernier_livraison = now();
            $fournisseur->save();

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
                            'motif' => 'Ajout de produit',
                        ],[
                            'date' => now(),
                        ]);
            //create historique action
            HistoriqueAction::create([
                'user_id' => Auth::user()->id,
                'produit_id' => $produit->id,
                'action' => 'Création de produit',
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
        try {
            $produit = Produit::findOrFail($id);
            return $produit;
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
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
        //create historique action
        HistoriqueAction::create([
            'user_id' => Auth::user()->id,
            'produit_id' => $produit->id,
            'action' => 'Modification de produit',
        ]);
        return response()->json($produit);
    }



    public function destroy(string $id)
    {
        try {
            $produit = Produit::findOrFail($id);
            $produit->produits()->update(['categorie_id' => null]);
            $produit->delete();
            //create historique action
            HistoriqueAction::create([
                'user_id' => Auth::user()->id,
                'produit_id' => $produit->id,
                'action' => 'Suppression de produit',
            ]);
            return response()->noContent();
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function reduireStockProduit(string $id, Request $request)
    {
        $produit = Produit::findOrFail($id);
        if($produit->nombre_carton < $request->quantite){
            return response()->json(['message' => 'Quantite superieure au stock disponible'], 400);
        }
        $data = $request->validate([
            'quantite' => 'required|integer',
        ]);
        $produit->decrement('nombre_carton',$data['quantite']);

        if($produit->stock_global < $data['quantite']*$produit->unite_carton){
            $produit->stock_global=0;
            $produit->save();
        }else{
            $produit->decrement('stock_global',$data['quantite']*$produit->unite_carton);
        }

        MouvementStock::firstOrCreate([
            'source' => 'boutique:' . Auth::user()->boutique_id,
            'destination' => 'depot',
            'produit_id' => $produit->id,
            'quantite' => $data['quantite'],
            'type' => 'Sortie',
            'motif' => 'Reduction de stock',
        ],[
            'date' => now(),
        ]);
        //create historique action
        HistoriqueAction::create([
            'user_id' => Auth::user()->id,
            'produit_id' => $produit->id,
            'action' => 'Reduction de stock',
        ]);

        $entree_sortie=EntreeSortie::firstOrCreate([
            'produit_id'  => $produit->id,
        ], [
            'quantite_avant' => 0,
            'quantite_apres' => 0,
            'nombre_fois'=>0
        ]);
        $entree_sortie->quantite_avant=$entree_sortie->quantite_apres;
        $entree_sortie->decrement('quantite_apres',$data['quantite']);
        $entree_sortie->increment('nombre_fois',1);
        $entree_sortie->save();
        return response()->json($produit);
    }
}

