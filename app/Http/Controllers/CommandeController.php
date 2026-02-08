<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Events\CommandeValidee;
use App\Events\CommandeAnnulee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    // =========================================================
    // 📦 LISTE DES COMMANDES (pagination + filtres + stats)
    // =========================================================
    public function index(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | BASE QUERY (filtres communs)
            |--------------------------------------------------------------------------
            */
            $baseQuery = Commande::query()
                ->with(['details', 'client', 'vendeur', 'paiements']);

            // filtre statut
            if ($request->filled('statut') && $request->statut !== 'tous') {
                $baseQuery->where('statut', $request->statut);
            }

            // filtre dates
            if ($request->filled('start_date')) {
                $baseQuery->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $baseQuery->whereDate('date', '<=', $request->end_date);
            }

            // recherche
            if ($request->filled('search')) {
                $s = $request->search;

                $baseQuery->where(function ($q) use ($s) {
                    $q->where('id', 'like', "%$s%")
                        ->orWhereHas('client', fn($c) =>
                            $c->where('nom', 'like', "%$s%")
                        )
                        ->orWhereHas('details.produit', function ($p) use ($s) {
                            $p->where('nom', 'like', "%$s%")
                              ->orWhere('libelle', 'like', "%$s%")
                              ->orWhere('reference', 'like', "%$s%");
                        });
                });
            }

            /*
            |--------------------------------------------------------------------------
            | TABLE QUERY (règle métier client spécial)
            |--------------------------------------------------------------------------
            */
            $tableQuery = clone $baseQuery;

            if ($request->filled('type_client') && $request->type_client === 'special') {
                $tableQuery->where(function ($q) {
                    $q->whereHas('client', fn($c) =>
                        $c->where('type_client', 'special')
                    )
                    ->orWhere(function ($x) {
                        $x->whereNull('client_id')
                          ->where('type_vente', 'gros');
                    });
                });
            }

            if ($request->filled('client_id')) {
                $tableQuery->where('client_id', $request->client_id);
            }

            $perPage = (int) $request->get('perPage', 20);

            $table = $tableQuery
                ->orderByDesc('date')
                ->paginate($perPage);

            /*
            |--------------------------------------------------------------------------
            | STATS QUERY (MÊME LOGIQUE QUE TABLE)
            |--------------------------------------------------------------------------
            */
            $statsQuery = clone $baseQuery;

            if ($request->filled('type_client') && $request->type_client === 'special') {
                $statsQuery->where(function ($q) {
                    $q->whereHas('client', fn($c) =>
                        $c->where('type_client', 'special')
                    )
                    ->orWhere(function ($x) {
                        $x->whereNull('client_id')
                          ->where('type_vente', 'gros');
                    });
                });
            }

            if ($request->filled('client_id')) {
                $statsQuery->where('client_id', $request->client_id);
            }

            // récupération non paginée pour stats
            $statsCollection = $statsQuery->get();

            $totalTTC = $statsCollection->sum('total');

            $totalPaye = $statsCollection
                ->sum(fn ($c) => $c->paiements->sum('montant'));

            $dette = max($totalTTC - $totalPaye, 0);


            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */
            return response()->json([
                'data' => $table->items(),
                'current_page' => $table->currentPage(),
                'last_page' => $table->lastPage(),
                'total' => $table->total(),

                'stats' => [
                    'nb' => $statsCollection->count(),
                    'annulees' => $statsCollection
                        ->where('statut', 'annulee')
                        ->count(),
                    'totalTTC' => $totalTTC,
                    'totalPaye' => $totalPaye,
                    'dette' => $dette,
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Erreur chargement commandes',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // 🛒 CRÉATION COMMANDE
    // =========================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable|uuid|exists:clients,id',
            'type_vente' => 'required|in:detail,gros',
            'tva' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.produit_id' => 'required|uuid|exists:produits,id',
            'items.*.quantite' => 'required|integer|min:1',
            'items.*.prix_unitaire' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $tva = $validated['tva'] ?? 0.18;

        return DB::transaction(function () use ($validated, $user, $tva) {

            $commande = Commande::create([
                'client_id' => $validated['client_id'] ?? null,
                'vendeur_id' => $user->id,
                'type_vente' => $validated['type_vente'],
                'statut' => 'en_attente_caisse',
                'total' => 0,
                'date' => now(),
            ]);

            $totalHt = 0;

            foreach ($validated['items'] as $item) {

                $produit = Produit::findOrFail($item['produit_id']);

                $prix = $item['prix_unitaire']
                    ?? ($validated['type_vente'] === 'gros' && $produit->prix_gros
                        ? $produit->prix_gros
                        : $produit->prix_vente);

                $ligneTotal = $prix * $item['quantite'];
                $totalHt += $ligneTotal;

                DetailCommande::create([
                    'commande_id' => $commande->id,
                    'produit_id' => $produit->id,
                    'quantite' => $item['quantite'],
                    'prix_unitaire' => $prix,
                ]);
            }

            $commande->update([
                'total' => $totalHt * (1 + $tva)
            ]);

            $commande->load('details.produit', 'vendeur', 'client');

            event(new CommandeValidee($commande));

            return $commande;
        });
    }

    public function show(string $id)
    {
        return Commande::with([
            'details.produit',
            'client',
            'vendeur',
            'paiements'
        ])->findOrFail($id);
    }

    public function destroy(string $id)
    {
        Commande::findOrFail($id)->delete();
        return response()->noContent();
    }

    // =========================================================
    // ❌ ANNULATION COMMANDE
    // =========================================================
    public function annuler(string $id)
    {
        $commande = Commande::findOrFail($id);

        $commande->update([
            'statut' => 'annulee'
        ]);

        event(new CommandeAnnulee($commande));

        return $commande;
    }
}
