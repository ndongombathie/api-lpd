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
                ->with([
                    'details',
                    'client',
                    'vendeur',
                    'paiements' => function ($q) use ($request) {

                        if ($request->filled('paiement_type')) {
                            $q->where('type_paiement', $request->paiement_type);
                        }

                        if ($request->filled('paiement_date')) {
                            $q->whereDate('date', $request->paiement_date);
                        }

                        $q->orderBy('date');
                    }
                ]);


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
            if ($request->filled('paiement_type')) {
                $baseQuery->whereHas('paiements', function ($q) use ($request) {
                    $q->where('type_paiement', $request->paiement_type);
                });
            }

            if ($request->filled('paiement_date')) {
                $baseQuery->whereHas('paiements', function ($q) use ($request) {
                    $q->whereDate('date', $request->paiement_date);
                });
            }
            // recherche
            if ($request->filled('search')) {
                $s = $request->search;

                $baseQuery->where(function ($q) use ($s) {

                    // numéro commande
                    $q->where('numero', 'like', "%$s%")


                    // nom client
                    ->orWhereHas('client', function ($c) use ($s) {
                        $c->where('nom', 'like', "%$s%");
                    })

                    // nom produit
                    ->orWhereHas('details.produit', function ($p) use ($s) {
                        $p->where('nom', 'like', "%$s%");
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
                $tableQuery->whereHas('client', fn($c) =>
                    $c->where('type_client', 'special')
                );
            }

            if ($request->filled('client_id')) {
                $tableQuery->where('client_id', $request->client_id);
            }

            $perPage = (int) $request->get('perPage', 20);

            $table = $tableQuery
                ->orderByDesc('date')
                ->paginate($perPage);
            $table->getCollection()->transform(function ($cmd) {

                $totalPaye = $cmd->montantPaye();
                $reste = $cmd->resteAPayer();

                return [
                    'id' => $cmd->id,
                    'numero' => $cmd->numero ?? $cmd->id,                    
                    'dateCommande' => $cmd->date,
                    'statut' => $cmd->statut,
                    'type_vente' => $cmd->type_vente,

                    // ✅ CHAMPS MÉTIER
                    'totalTTC' => $cmd->total,
                    'montantPaye' => $totalPaye,
                    'resteAPayer' => $reste,

                    'clientId' => $cmd->client_id,
                    'lignes' => $cmd->details->map(function ($d) {
                        return [
                            'id' => $d->id,
                            'produit_id' => $d->produit_id,
                            'libelle' => $d->produit->nom ?? null,
                            'ref' => $d->produit->reference ?? null,
                            'quantite' => $d->quantite,
                            'prix_unitaire' => $d->prix_unitaire,
                            'total_ttc' => $d->prix_unitaire * $d->quantite,
                        ];
                    }),


                    'paiements' => $cmd->paiements->map(function ($p) {
                        return [
                            'id' => $p->id,
                            'montant' => $p->montant,
                            'type_paiement' => $p->type_paiement,
                            'date' => $p->date,
                            'reste_du' => $p->reste_du,
                        ];
                    }),

                    'client' => $cmd->client,
                    'vendeur' => $cmd->vendeur,
                ];
            });

            /*
            |--------------------------------------------------------------------------
            | STATS QUERY (MÊME LOGIQUE QUE TABLE)
            |--------------------------------------------------------------------------
            */
            $statsQuery = clone $baseQuery;

            if ($request->filled('type_client') && $request->type_client === 'special') {
                $statsQuery->whereHas('client', fn($c) =>
                    $c->where('type_client', 'special')
                );
            }

            if ($request->filled('client_id')) {
                $statsQuery->where('client_id', $request->client_id);
            }

            // récupération non paginée pour stats
            $statsCollection = $statsQuery->get();

            // ===============================
            // LOGIQUE CLIENTS SPECIAUX
            // ===============================

            if ($request->statut === 'annulee') {

                // MODE TRAÇABILITÉ (théorique)
                $annulees = $statsCollection->where('statut', 'annulee');

                $totalTTC = $annulees->sum('total');
                $totalPaye = 0;
                $dette = $totalTTC;

            } else {

                // MODE NORMAL (financier réel)
                $actives = $statsCollection->where('statut', '!=', 'annulee');

                $totalTTC = $actives->sum('total');
                $totalPaye = $actives->sum(fn ($c) => $c->montantPaye());
                $dette = max($totalTTC - $totalPaye, 0);
            }



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
            $lastNumero = Commande::max('numero');

            $next = $lastNumero
                ? ((int) substr($lastNumero, 4)) + 1
                : 1;

            $commande->numero = 'CMD-' . str_pad($next, 6, '0', STR_PAD_LEFT);


            $commande->save();


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