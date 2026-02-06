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
    // 📦 LISTE DES COMMANDES (pagination + filtres + stats globales)
    // =========================================================
    public function index(Request $request)
    {
        try {
            $baseQuery = Commande::with(['details', 'client', 'vendeur', 'paiements']);

            // =====================================================
            // 1️⃣ FILTRES COMMUNS (statut, dates, recherche)
            // =====================================================
            if ($request->filled('statut') && $request->statut !== 'tous') {
                $baseQuery->where('statut', $request->statut);
            }

            if ($request->filled('start_date')) {
                $baseQuery->whereDate('date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $baseQuery->whereDate('date', '<=', $request->end_date);
            }

            if ($request->filled('search')) {
                $s = $request->search;
                $baseQuery->where(function ($q) use ($s) {
                    $q->where('id', 'like', "%$s%")
                      ->orWhereHas('client', fn($c) => $c->where('nom', 'like', "%$s%"))
                      ->orWhereHas('details.produit', function ($p) use ($s) {
                          $p->where('nom', 'like', "%$s%")
                            ->orWhere('libelle', 'like', "%$s%")
                            ->orWhere('reference', 'like', "%$s%");
                      });
                });
            }

            // =====================================================
            // 2️⃣ REQUÊTE TABLE (règle métier client spécial)
            // =====================================================
            $tableQuery = clone $baseQuery;

            if ($request->filled('type_client') && $request->type_client === 'special') {
                $tableQuery->where(function ($q) {
                    $q->whereHas('client', fn($c) => $c->where('type_client', 'special'))
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
            $table = $tableQuery->orderByDesc('date')->paginate($perPage);

            // =====================================================
            // 3️⃣ REQUÊTE STATS (MÊME règle métier que la table)
            // =====================================================
            $statsQuery = clone $baseQuery;

            if ($request->filled('type_client') && $request->type_client === 'special') {
                $statsQuery->where(function ($q) {
                    $q->whereHas('client', fn($c) => $c->where('type_client', 'special'))
                      ->orWhere(function ($x) {
                          $x->whereNull('client_id')
                            ->where('type_vente', 'gros');
                      });
                });
            }

            $stats = $statsQuery->get();

            return response()->json([
                'data' => $table->items(),
                'current_page' => $table->currentPage(),
                'last_page' => $table->lastPage(),
                'total' => $table->total(),

                // 🧮 Stats réelles (non paginées)
                'stats' => [
                    'nb' => $stats->count(),
                    'annulees' => $stats->where('statut', 'annulee')->count(),
                    'totalTTC' => $stats->sum('total'),
                    'totalPaye' => $stats->sum(fn($c) => $c->paiements->sum('montant')),
                    'dette' => $stats
                        ->whereIn('statut', ['en_attente_caisse', 'partiellement_payee'])
                        ->sum(fn($c) => max($c->total - $c->paiements->sum('montant'), 0)),
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
    // 🛒 CRÉATION COMMANDE (module vendeur intact)
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
        return Commande::with(['details.produit', 'client', 'vendeur', 'paiements'])->findOrFail($id);
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
