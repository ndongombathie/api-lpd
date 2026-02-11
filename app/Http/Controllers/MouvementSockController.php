<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\EntreeSortie;
use App\Models\Inventaire;
use App\Models\MouvementStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MouvementSockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    protected $inventaireController;
    public function __construct(InventaireController $inventaireController) {
        $this->inventaireController = $inventaireController;
    }
    public function index(Request $request)
    {
        try {
            $query = MouvementStock::query()->with('produit');

            if ($request->filled('date_debut')) {
                $query->whereDate('date', '>=', $request->date_debut);
            }

            if ($request->filled('date_fin')) {
                $query->whereDate('date', '<=', $request->date_fin);
            }

            #filtred by type
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }
            #filtred by produit_id
            if ($request->filled('produit_id')) {
                $query->where('produit_id', $request->produit_id);
            }

            $mouvements = $query->latest()->paginate(10);
            $mouvements->getCollection()->transform(function ($mouvement) {
                $mouvement->entree_sortie = EntreeSortie::where('produit_id', $mouvement->produit_id)->first();
                return $mouvement;
            });
            return response()->json($mouvements);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }



    public function inventaireDepot(Request $request)
    {
        try {
            $query = DB::table('mouvement_stocks')
                ->join('produits', 'mouvement_stocks.produit_id', '=', 'produits.id')
                ->select(
                    'produits.id',
                    'produits.nom',
                    'produits.categorie_id',
                    'produits.prix_unite_carton',
                    'produits.prix_achat',
                    DB::raw("SUM(CASE WHEN mouvement_stocks.type = 'entree' THEN mouvement_stocks.quantite ELSE 0 END) as total_entree"),
                    DB::raw("SUM(CASE WHEN mouvement_stocks.type = 'sortie' THEN mouvement_stocks.quantite ELSE 0 END) as total_sortie")
                )
                ->groupBy('produits.id', 'produits.nom', 'produits.categorie_id', 'produits.prix_unite_carton', 'produits.prix_achat');

            if ($request->filled('date_debut')) {
                $query->whereDate('mouvement_stocks.date', '>=', $request->date_debut);
            }
            if ($request->filled('date_fin')) {
                $query->whereDate('mouvement_stocks.date', '<=', $request->date_fin);
            }

            $inventaire = $query->paginate(15);

            $inventaire->getCollection()->transform(function ($item) {
                $entrees=EntreeSortie::where('produit_id',$item->id)->get()->first();
                $item->categorie = $item->categorie_id ? Categorie::find($item->categorie_id)->nom : null;
                $item->stock_restant = $item->total_entree - $item->total_sortie < 0 ? 0 : $item->total_entree - $item->total_sortie;
                $prix = $item->prix_achat > 0 ? $item->prix_achat : 0;
                $item->valeur_sortie = $item->total_sortie * $prix;
                $item->valeur_estimee = $item->stock_restant * $prix;
                $item->nombre_app=$entrees?-> nombre_fois ?? 0;
                return $item;
            });

            return $inventaire;
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    #a partir de inventaireDepot calculer l'inventaireDeopt faire la somme de prix_achat_total,prix_valeur_sortie_total,valeur_estimee_total et le benefice_total
    public function enregistrerInventaireDepot(Request $request)
    {
        try {
            $inventaire = $this->inventaireDepot($request);
            $total = $inventaire->reduce(function ($carry, $item) {

                $entree = (int) $item->total_entree;
                $sortie = (int) $item->total_sortie;
                $stock  = (int) $item->stock_restant;
                $prix   = (float) $item->prix_achat;

                $carry['prix_achat_total'] += $entree * $prix;
                $carry['prix_valeur_sortie_total'] += $sortie * $prix;
                $carry['valeur_estimee_total'] += $stock * $prix;

                return $carry;

                }, [
                    'prix_achat_total' => 0,
                    'prix_valeur_sortie_total' => 0,
                    'valeur_estimee_total' => 0,
                ]);

                $total['benefice_total'] =
                    $total['prix_valeur_sortie_total'] - $total['prix_achat_total'];

                Inventaire::create([
                    'type' => 'Depot',
                    'date_debut' => $request->date_debut ?? now(),
                    'date_fin' => $request->date_fin ?? now(),
                    'date' => now(),
                    'prix_achat_total' => $total['prix_achat_total'],
                    'prix_valeur_sortie_total' => $total['prix_valeur_sortie_total'],
                    'valeur_estimee_total' => $total['valeur_estimee_total'],
                    'benefice_total' => $total['benefice_total'],
                ]);

                return response()->json($total);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
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
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
