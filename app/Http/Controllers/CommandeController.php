<?php 

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommandeController extends Controller
{
    public function index()
    {
        return Commande::query()
            ->with(['details.produit'])
            ->latest()
            ->paginate(20);
    }

    public function getCommandesEnAttente()
    {
        return Commande::query()
            ->where('statut', 'brouillon')
            ->with(['details.produit'])
            ->latest()
            ->paginate(20);
    }

    public function getCommandesValidees()
    {
        return Commande::query()
            ->where('statut', 'validee')
            ->with(['details.produit'])
            ->latest()
            ->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     *
     * On accepte 2 formats :
     *  - nouveau format "lignes" (module Commandes Responsable)
     *  - ancien format "items" (module existant)
     */
    public function store(Request $request)
    {
        // ✅ Nouveau format (Commandes.jsx côté Responsable)
        if ($request->has('lignes')) {
            return $this->storeFromResponsable($request);
        }

        // ✅ Ancien format (items + type_vente) — compatibilité
        return $this->storeLegacy($request);
    }

    /**
     * Nouveau flux : création de commandes depuis le module Responsable
     * avec lignes[mode_vente, quantite_unites, ...] et décrémentation du stock.
     *
     * Payload attendu depuis React (Commandes.jsx) :
     * {
     *   "client_id": "...",
     *   "date_commande": "2025-12-07",
     *   "appliquer_tva": 1,
     *   "lignes": [
     *     {
     *       "produit_id": "uuid|null",
     *       "libelle": "Cahier 200p",
     *       "quantite": 5,                // unités OU cartons selon mode_vente
     *       "mode_vente": "detail|gros",  // optionnel côté front, 'detail' par défaut
     *       "quantite_unites": 120,       // optionnel : si pas envoyé, on calcule
     *       "prix_unitaire": 500,
     *       "total_ht": 2500,
     *       "total_ttc": 2950
     *     }
     *   ]
     * }
     */
    protected function storeFromResponsable(Request $request)
    {
        $validated = $request->validate([
            'client_id'       => 'nullable|uuid|exists:clients,id',
            'date_commande'   => 'nullable|date',
            'total_ht'        => 'nullable|numeric',
            'total_tva'       => 'nullable|numeric',
            'total_ttc'       => 'nullable|numeric',
            'appliquer_tva'   => 'nullable|boolean',
            'statut'          => 'nullable|in:en_attente_caisse,partiellement_payee,soldee,annulee',

            'lignes'                      => 'required|array|min:1',
            'lignes.*.produit_id'        => 'nullable|uuid|exists:produits,id',
            'lignes.*.libelle'           => 'required|string',
            'lignes.*.quantite'          => 'required|integer|min:1',
            'lignes.*.quantite_unites'   => 'nullable|integer|min:1',
            'lignes.*.mode_vente'        => 'nullable|in:detail,gros',
            'lignes.*.prix_unitaire'     => 'required|numeric|min:0',
            'lignes.*.total_ht'          => 'nullable|numeric',
            'lignes.*.total_ttc'         => 'nullable|numeric',
        ]);

        $user = $request->user();

        // TVA : on part sur 18 % si appliquer_tva = true, sinon 0
        $appliquerTVA = $request->boolean('appliquer_tva', true);
        $tvaRate      = $appliquerTVA ? 0.18 : 0.0;

        return DB::transaction(function () use ($validated, $user, $tvaRate, $appliquerTVA) {

            $commande = Commande::create([
                'client_id'  => $validated['client_id'] ?? null,
                'vendeur_id' => $user->id,            // le responsable qui crée la commande
                // La commande peut contenir du détail + du gros, on garde "gros" par défaut pour ne pas casser la colonne
                'type_vente' => 'gros',
                'statut'     => $validated['statut'] ?? 'en_attente_caisse',
                'total'      => 0,
                'date'       => $validated['date_commande'] ?? now(),
            ]);

            $totalHt = 0;

            foreach ($validated['lignes'] as $ligne) {
                // Produit facultatif (ligne libre possible)
                $produit = null;
                if (!empty($ligne['produit_id'])) {
                    // On verrouille pour éviter la concurrence sur le stock
                    $produit = Produit::lockForUpdate()->find($ligne['produit_id']);
                }

                $modeVente     = $ligne['mode_vente'] ?? 'detail'; // par défaut : détail
                $quantiteAff   = (int) $ligne['quantite'];         // quantité saisie (unités ou cartons)
                $prixUnitaire  = (float) $ligne['prix_unitaire'];

                // Total HT / TTC de la ligne (sécurisation si front ne les a pas envoyés)
                $ligneTotalHt = isset($ligne['total_ht'])
                    ? (float) $ligne['total_ht']
                    : $quantiteAff * $prixUnitaire;

                $ligneTotalTtc = isset($ligne['total_ttc'])
                    ? (float) $ligne['total_ttc']
                    : $ligneTotalHt * (1 + $tvaRate);

                // 🔢 Quantité en unités réelles pour le stock
                if (isset($ligne['quantite_unites'])) {
                    $quantiteUnites = (int) $ligne['quantite_unites'];
                } elseif ($produit && $modeVente === 'gros' && $produit->unites_par_carton) {
                    // Vente en gros : on convertit cartons -> unités
                    $quantiteUnites = $quantiteAff * (int) $produit->unites_par_carton;
                } else {
                    // Vente au détail : quantité = unités
                    $quantiteUnites = $quantiteAff;
                }

                $totalHt += $ligneTotalHt;

                // 🧾 Création du détail de commande
                DetailCommande::create([
                    'commande_id'     => $commande->id,
                    'produit_id'      => $produit?->id,
                    'libelle'         => $ligne['libelle'],
                    'mode_vente'      => $modeVente,         // "detail" | "gros"
                    'quantite'        => $quantiteAff,       // cartons OU unités affichées
                    'quantite_unites' => $quantiteUnites,    // unités réelles pour le stock
                    'prix_unitaire'   => $prixUnitaire,
                    'total_ht'        => $ligneTotalHt,
                    'total_ttc'       => $ligneTotalTtc,
                ]);

                // 📉 Mise à jour du stock produit
                if ($produit) {
                    $produit->stock_global = max(
                        0,
                        (int) $produit->stock_global - $quantiteUnites
                    );

                    if (!empty($produit->unites_par_carton) && $produit->unites_par_carton > 0) {
                        $produit->nombre_cartons = intdiv(
                            $produit->stock_global,
                            $produit->unites_par_carton
                        );
                    }

                    $produit->save();
                }
            }

            $montantTva = $totalHt * $tvaRate;
            $totalTtc   = $totalHt + $montantTva;

            // Si ta table Commandes a des colonnes séparées (total_ht, total_tva, total_ttc),
            // tu pourras aussi les remplir ici plus tard. Pour l'instant on garde "total".
            $commande->update([
                'total' => $totalTtc,
            ]);

            return $commande->load(['details.produit']);
        });
    }

    /**
     * Ancien flux : items + type_vente (on laisse tel quel pour compatibilité)
     *
     * Payload historique :
     * {
     *   "client_id": "...",
     *   "type_vente": "detail|gros",
     *   "tva": 0.18,
     *   "items": [
     *     {
     *       "produit_id": "uuid",
     *       "quantite": 3,
     *       "prix_unitaire": 500
     *     }
     *   ]
     * }
     */
    protected function storeLegacy(Request $request)
    {
        $validated = $request->validate([
            'client_id'              => 'nullable|uuid|exists:clients,id',
            'type_vente'             => 'required|in:detail,gros',
            'tva'                    => 'nullable|numeric',
            'items'                  => 'required|array|min:1',
            'items.*.produit_id'     => 'required|uuid|exists:produits,id',
            'items.*.quantite'       => 'required|integer|min:1',
            'items.*.prix_unitaire'  => 'nullable|numeric',
        ]);

        $user = $request->user();
        $tva  = $validated['tva'] ?? 0.18; // 18% par défaut

        return DB::transaction(function () use ($validated, $user, $tva) {
            $commande = Commande::create([
                'client_id'  => $validated['client_id'] ?? null,
                'vendeur_id' => $user->id,
                'type_vente' => $validated['type_vente'],
                'statut'     => 'validee',
                'total'      => 0,
                'date'       => now(),
            ]);

            $totalHt = 0;

            foreach ($validated['items'] as $item) {
                $produit = Produit::findOrFail($item['produit_id']);

                $prix = $item['prix_unitaire']
                    ?? ($validated['type_vente'] === 'gros' && $produit->prix_gros
                        ? $produit->prix_gros
                        : $produit->prix_vente);

                $ligneTotal = $prix * $item['quantite'];
                $totalHt   += $ligneTotal;

                // On remplit aussi les nouvelles colonnes de detail_commandes
                $modeVente  = $validated['type_vente']; // "detail" ou "gros"
                $quantite   = (int) $item['quantite'];

                if ($modeVente === 'gros' && $produit->unites_par_carton) {
                    $quantiteUnites = $quantite * (int) $produit->unites_par_carton;
                } else {
                    $quantiteUnites = $quantite;
                }

                DetailCommande::create([
                    'commande_id'     => $commande->id,
                    'produit_id'      => $produit->id,
                    'libelle'         => $produit->nom,
                    'mode_vente'      => $modeVente,
                    'quantite'        => $quantite,
                    'quantite_unites' => $quantiteUnites,
                    'prix_unitaire'   => $prix,
                    'total_ht'        => $ligneTotal,
                    'total_ttc'       => $ligneTotal * (1 + $tva),
                ]);

                // ⚠️ Ici je ne touche PAS encore au stock pour ne pas casser ton flux existant.
                // Si tu veux aligner totalement, on pourra décrémenter ici aussi, comme dans storeFromResponsable.
            }

            $montantTva = $totalHt * $tva;
            $commande->update(['total' => $totalHt + $montantTva]);

            return $commande->load(['details.produit']);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Commande::with(['details.produit'])->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $commande = Commande::findOrFail($id);

        $data = $request->validate([
            // On accepte les anciens + nouveaux statuts
            'statut' => 'sometimes|in:brouillon,validee,payee,annulee,en_attente_caisse,partiellement_payee,soldee',
        ]);

        $commande->update($data);

        return $commande->load(['details.produit']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->delete();

        return response()->noContent();
    }

    public function valider(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->update(['statut' => 'validee']);

        return $commande->load(['details.produit']);
    }

    public function annuler(string $id)
    {
        $commande = Commande::findOrFail($id);
        $commande->update(['statut' => 'annulee']);

        return $commande->load(['details.produit']);
    }
}
