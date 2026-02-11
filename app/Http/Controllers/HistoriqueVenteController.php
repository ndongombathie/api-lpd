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
            $query = HistoriqueVente::query()->latest();
            // Filter by role if provided
            if ($request->filled('vendeur_id')) {
                $query->where('vendeur_id', $request->input('vendeur_id'));
            }

            // Filter by boutique_id if provided
            if ($request->filled('produit_id')) {
                $query->where('produit_id', $request->input('produit_id'));
            }

            // Filter by search term if provided
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('montant', 'like', "%{$search}%")
                      ->orWhere('quantite', 'like', "%{$search}%")
                      ->orWhere('prix_unitaire', 'like', "%{$search}%");
                });
            }
            $historiqueVentes = $query->with(['vendeur', 'produit'])->paginate(10);
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
                $produit->produit=Produit::query()->with('entreees_sorties_boutique')->where('id',$produit->produit_id)->get()->first();
                $produit->total_vendu=$produit->quantite_vendue*$produit->produit->prix_unite_carton;
                $produit->total_resant=($produit->stock_initial-$produit->quantite_vendue)*$produit->produit->prix_unite_carton > 0 ? ($produit->stock_initial-$produit->quantite_vendue)*$produit->produit->prix_unite_carton  : 0;
                return $produit;
            });

            return response()->json(['date' => $date, 'produits' => $produitsVendus]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    #a partir de inventaireDepot calculer l'inventaireBoutique faire la somme de prix_achat_total,prix_valeur_sortie_total,valeur_estimee_total et le benefice_total
    public function enregistrerInventaireBoutique(Request $request)
    {
        try {
            $inventaire = $this->inventaireBoutique($request);
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
