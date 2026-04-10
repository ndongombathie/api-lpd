<?php

namespace App\Http\Controllers;

use App\Models\HistoriqueVente;
use App\Models\Inventaire;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class HistoriqueVenteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = HistoriqueVente::with(['vendeur', 'produit'])
                ->orderBy('created_at', 'asc');

            if ($request->filled('vendeur_id')) {
                $query->where('vendeur_id', $request->input('vendeur_id'));
            }

            if ($request->filled('produit_id')) {
                $query->where('produit_id', $request->input('produit_id'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('montant', 'like', "%{$search}%")
                      ->orWhere('quantite', 'like', "%{$search}%")
                      ->orWhere('prix_unitaire', 'like', "%{$search}%");
                });
            }

            $historiqueVentes = $query->paginate(10);
            return response()->json($historiqueVentes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function inventaireBoutique(Request $request)
    {

        try {
            //dd($request);
            // Récupérer les produits vendus à la date donnée avec la quantité totale vendue
            $query = DB::table('historique_ventes')
                ->join('transfert_en_attentes', 'historique_ventes.produit_id', '=', 'transfert_en_attentes.produit_id')
                ->select(
                    'transfert_en_attentes.produit_id',
                    'transfert_en_attentes.quantite_initial as stock_initial',
                    DB::raw('SUM(historique_ventes.quantite) as quantite_vendue')
                )
                ->groupBy('historique_ventes.produit_id');


            if($request->filled('date_debut')) {
                $query->whereDate('historique_ventes.date', '>=', $request->date_debut);
            }

            if ($request->filled('date_fin')) {
                $query->whereDate('historique_ventes.date', '<=', $request->date_fin);
            }

            $produitsVendus = $query->paginate(10);

            $ids = $produitsVendus->getCollection()->pluck('produit_id')->unique()->values();
            $produitsMap = Produit::with('entreees_sorties_boutique')
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id');

            $produitsVendus->getCollection()->transform(function ($produit) use ($produitsMap) {
                $produit->ecart = $produit->stock_initial - $produit->quantite_vendue;
                $produit->produit = $produitsMap->get($produit->produit_id);
                $produit->total_vendu = $produit->quantite_vendue * $produit->produit->prix_unite_carton;
                $resteBrut = ($produit->stock_initial - $produit->quantite_vendue) * $produit->produit->prix_unite_carton;
                $produit->total_resant = $resteBrut > 0 ? $resteBrut : 0;
                return $produit;
            });

            return ['produits' => $produitsVendus];

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    #a partir de inventaireDepot calculer l'inventaireBoutique faire la somme de prix_achat_total,prix_valeur_sortie_total,valeur_estimee_total et le benefice_total
    public function enregistrerInventaireBoutique(Request $request)
    {
        try {
                $paginator = $this->inventaireBoutique($request)['produits'];
                $collection = $paginator->getCollection();
                $total = $collection->reduce(function ($carry, $item) {

                $mouvement = $item->produit->entreees_sorties_boutique->first();

                $entree = (int) ($item->stock_initial ?? 0);
                $sortie = (int) ($item->quantite_vendue ?? 0);
                $stock  = (int) $item->total_resant;
                $prix   = (float) $item->produit->prix_unite_carton;

                $carry['prix_achat_total'] += $entree * $prix;
                $carry['prix_valeur_sortie_total'] += $sortie * $prix;
                $carry['valeur_estimee_total'] += $stock;

                return $carry;

                }, [
                    'prix_achat_total' => 0,
                    'prix_valeur_sortie_total' => 0,
                    'valeur_estimee_total' => 0,
                ]);

                $total['benefice_total'] =
                    $total['prix_valeur_sortie_total'] - $total['prix_achat_total'];

                Inventaire::create([
                    'type' => 'Boutique',
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
     * Get the total sales for a given day.
     */
    public function totalParJour(Request $request)
    {


        $date = $request->input('date') ?? Carbon::now()->format('Y-m-d');

        try {
            $total = HistoriqueVente::query();
            if($request->filled('date')){
                $total = $total->whereDate('created_at', $date)->sum('montant');
            }
            else{
                $total = $total->sum('montant');
            }

            return response()->json($total);
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

    #Nombre total de ventes par vendeur et Total encaissé par vendeur
    public function totalVentesParVendeur(Request $request)
    {
        try {

            $totalVentes = HistoriqueVente::with('vendeur')
                ->select(
                    'vendeur_id',
                    DB::raw('COUNT(quantite) as total_ventes'),
                    DB::raw('SUM(montant) as total_encaisses')
                );

            // ✅ Appliquer filtre seulement si dates envoyées
            if ($request->filled('date_debut') && $request->filled('date_fin')) {
                $totalVentes->whereBetween('date', [
                    $request->date_debut,
                    $request->date_fin
                ]);
            }

            $totalVentes->groupBy('vendeur_id');

            // filtre recherche
            if ($request->filled('search')) {
                $search = $request->input('search');

                $totalVentes->whereHas('vendeur', function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $queryTotal = clone $totalVentes;

            return response()->json([
                'total_ventes' => $totalVentes->paginate(10),
                'somme_total_encaisses' => $queryTotal->get()->sum('total_encaisses')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
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
