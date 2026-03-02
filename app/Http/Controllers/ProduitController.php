<?php

namespace App\Http\Controllers;

use App\Models\Commande;
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

    public function index(Request $request)
    {
        try {
            $query =  $this->repository->index();
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
                ->paginate(10);

            } catch (\Throwable $th) {
                return response()->json([
                    'message' => $th->getMessage()
                ], 500);
            }
    }


    public function produits_en_rupture()
    {
        try {

            return Produit::where('nombre_carton', 0)->paginate(10);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    #sous seuil
    public function produits_sous_seuil(Request $request){
        try {
            $query = Produit::whereColumn('nombre_carton', '<', 'stock_seuil');
            if($request->filled('search')){
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->whereHas('categorie', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%");
                    })
                    ->orWhere('quantite', 'like', "%{$search}%")
                    ->orWhere('seuil', 'like', "%{$search}%")
                    ->orWhere('nombre_carton', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%");
                });
            }
            return $query->paginate(10);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    #nombre sous seuil
    public function nombreProduitsSousSeuil(){
        try {
            $count = Produit::where('nombre_carton', '>', 0)
                ->whereColumn('nombre_carton', '<', 'stock_seuil')
                ->count();

            return response()->json($count);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    # nombre en normaux.
    public function nombreProduitsEnNormaux(){
        try {
            $count = Produit::whereColumn('nombre_carton', '>=', 'stock_seuil')
                ->count();

            return response()->json($count);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    #la liste des produits normales
    #filtrer par nom de produit,categorie
    public function produitsEnNormaux(Request $request)
    {
        try {
            $query = Produit::with('categorie')
                ->whereColumn('nombre_carton', '>', 'stock_seuil')
                ->latest();

            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {

                    // 🔍 Recherche par nom du produit
                    $q->where('nom', 'like', "%{$search}%");

                    // 🔍 Recherche par catégorie
                    $q->orWhereHas('categorie', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%");
                    });

                    // 🔍 Recherche numérique (si chiffre)
                    if (is_numeric($search)) {
                        $q->orWhere('quantite', $search)
                        ->orWhere('stock_seuil', $search)
                        ->orWhere('nombre_carton', $search);
                    }

                    // 🔍 Recherche par date
                    $q->orWhere('created_at', 'like', "%{$search}%");
                });
            }

            return response()->json($query->paginate(10));

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }


    #•	Nombre de produits en rupture (nombre_carton==0)
    public function nombreProduitsEnRupture(){
        try {
            $count = Produit::where('nombre_carton', 0)->count();

            return response()->json($count);
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
            'code' => 'required|string|unique:produits,code,'.$id,
            'categorie_id' => 'nullable|string',
            'fournisseur_id' => 'nullable|string',
            'unite_carton' => 'nullable|integer',
            'prix_unite_carton' => 'nullable|numeric',
            'nombre_carton' => 'nullable|integer',
            'stock_seuil' => 'nullable|integer',
            'prix_achat' => 'nullable|numeric',
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
            HistoriqueAction::create([
                'user_id' => Auth::user()->id,
                'produit_id' => $produit->id,
                'action' => 'Suppression de produit',
            ]);
            $produit->delete();
            //create historique action

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


    #nombre de produits total vendu aujourduih
    public function nombreProduitsVendusAujourdhui(){
        try {
            $nombreProduitsVendus = Commande::whereDate('created_at', date('Y-m-d'))
            ->with('details.produit')
            ->where('statut', 'payee')
            ->get()
            ->sum(function ($commande) {
                return $commande->details->sum('quantite');
            });
            return response()->json($nombreProduitsVendus);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du nombre de produits vendus aujourd\'hui',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function nombreProduits(){
        try {
            return response()->json(Produit::count());
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    // ============================================================
    // LISTE COMPLETE PRODUITS (sans pagination)
    // ============================================================
    public function all()
    {
        return Produit::orderBy('nom')->get();
    }
}

