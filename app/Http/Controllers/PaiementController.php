<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    /**
     * Liste des paiements d'une commande.
     * Route : GET /commandes/{commande}/paiements
     */
    public function index(string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);

        return Paiement::where('commande_id', $commande->id)
            ->orderBy('date')
            ->get();
    }

    /**
     * Création d'un paiement pour une commande.
     *
     * - Cas 1 : tranche "en_attente_caisse" (préparée par le Responsable)
     *   → on NE touche PAS aux totaux de la commande.
     *
     * - Cas 2 : paiement réellement encaissé
     *   → on recalcule le reste et on peut mettre la commande à "payee".
     */
    public function store(Request $request, string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);

        $data = $request->validate([
            'montant'         => 'required|numeric|min:0.01',
            'type_paiement'   => 'required|string',
            'mode_paiement'   => 'nullable|string|max:50',
            'statut_paiement' => 'nullable|string|max:50',
            'date_paiement'   => 'nullable|date',
            'commentaire'     => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($commande, $data) {

            $isTrancheEnAttente =
                ($data['type_paiement']   ?? null) === 'tranche' &&
                ($data['statut_paiement'] ?? null) === 'en_attente_caisse';

            /**
             * 🟠 CAS 1 : tranche préparée par le Responsable (en_attente_caisse)
             * → on NE touche PAS aux totaux de la commande.
             * → on vérifie juste qu'on ne dépasse pas le reste théorique.
             */
            if ($isTrancheEnAttente) {

                // Montant déjà encaissé (paiements "réels" uniquement)
                $totalEncaisse = Paiement::where('commande_id', $commande->id)
                    ->where(function ($q) {
                        $q->whereNull('statut_paiement')
                          ->orWhere('statut_paiement', '!=', 'en_attente_caisse');
                    })
                    ->sum('montant');

                // Montant des tranches déjà en attente caisse
                $totalTranchesEnAttente = Paiement::where('commande_id', $commande->id)
                    ->where('type_paiement', 'tranche')
                    ->where('statut_paiement', 'en_attente_caisse')
                    ->sum('montant');

                // Reste théorique dispo AVANT la nouvelle tranche
                $resteTheoriqueAvant = max(
                    0,
                    $commande->total - $totalEncaisse - $totalTranchesEnAttente
                );

                if ($data['montant'] > $resteTheoriqueAvant) {
                    return response()->json([
                        'errors' => [
                            'montant' => [
                                "La tranche ne peut pas dépasser le reste théorique disponible (" .
                                number_format($resteTheoriqueAvant, 0, ',', ' ') . " F CFA).",
                            ],
                        ],
                    ], 422);
                }

                // 🔢 On fixe un reste_du cohérent (après cette tranche)
                $resteApresCetteTranche = max(0, $resteTheoriqueAvant - $data['montant']);

                $paiement = Paiement::create([
                    'commande_id'     => $commande->id,
                    'montant'         => $data['montant'],
                    'type_paiement'   => 'tranche',
                    'mode_paiement'   => $data['mode_paiement']   ?? null,
                    'statut_paiement' => 'en_attente_caisse',
                    'commentaire'     => $data['commentaire']     ?? null,
                    'date'            => $data['date_paiement']   ?? now(),
                    'reste_du'        => $resteApresCetteTranche, // 🌟 plus jamais NULL ici
                ]);

                return response()->json($paiement, 201);
            }

            /**
             * 🟢 CAS 2 : paiement encaissé "normal"
             */
            $totalPayeAvant = Paiement::where('commande_id', $commande->id)->sum('montant');
            $reste = max(0, $commande->total - $totalPayeAvant - $data['montant']);

            $paiement = Paiement::create([
                'commande_id'     => $commande->id,
                'montant'         => $data['montant'],
                'type_paiement'   => $data['type_paiement'],
                'mode_paiement'   => $data['mode_paiement']   ?? null,
                'statut_paiement' => $data['statut_paiement'] ?? null,
                'commentaire'     => $data['commentaire']     ?? null,
                'date'            => $data['date_paiement']   ?? now(),
                'reste_du'        => $reste,
            ]);

            if ($reste <= 0) {
                $commande->update(['statut' => 'payee']);
            }

            return response()->json($paiement, 201);
        });
    }

    /**
     * Afficher un paiement précis.
     */
    public function show(string $id)
    {
        return Paiement::findOrFail($id);
    }

    /**
     * 🔁 Mise à jour d'une tranche "en_attente_caisse"
     * (utilisée par VoirDetailClient côté Responsable).
     */
    public function update(Request $request, Paiement $paiement)
    {
        // On verrouille : seule une tranche en attente caisse est modifiable ici
        if (
            $paiement->type_paiement !== 'tranche' ||
            $paiement->statut_paiement !== 'en_attente_caisse'
        ) {
            return response()->json([
                'message' => "Seules les tranches en attente caisse sont modifiables depuis ce module.",
            ], 422);
        }

        $data = $request->validate([
            'montant'       => 'required|numeric|min:0.01',
            'mode_paiement' => 'nullable|string|max:50',
            'date_paiement' => 'nullable|date',
            'commentaire'   => 'nullable|string|max:255',
        ]);

        $paiement->update([
            'montant'       => $data['montant'],
            'mode_paiement' => $data['mode_paiement'] ?? $paiement->mode_paiement,
            'commentaire'   => $data['commentaire']   ?? $paiement->commentaire,
            'date'          => $data['date_paiement'] ?? $paiement->date,
            // on laisse statut_paiement = "en_attente_caisse"
        ]);

        return response()->json($paiement);
    }

    /**
     * 🗑️ Suppression d'une tranche en attente caisse.
     */
    public function destroy(Paiement $paiement)
    {
        if (
            $paiement->type_paiement !== 'tranche' ||
            $paiement->statut_paiement !== 'en_attente_caisse'
        ) {
            return response()->json([
                'message' => "Seules les tranches en attente caisse peuvent être supprimées depuis ce module.",
            ], 422);
        }

        $paiement->delete();

        return response()->json([
            'message' => 'Tranche supprimée avec succès.',
        ]);
    }
    /**
     * ✅ Encaisser une tranche en attente caisse (côté caisse)
     *
     * Route (à ajouter) :
     * POST /paiements/{paiement}/encaisser
     */
    public function encaisser(Request $request, Paiement $paiement)
    {
        // On vérifie qu'on encaisse bien une tranche en attente caisse
        if (
            $paiement->type_paiement !== 'tranche' ||
            $paiement->statut_paiement !== 'en_attente_caisse'
        ) {
            return response()->json([
                'message' => "Seules les tranches en attente caisse peuvent être encaissées.",
            ], 422);
        }

        $commande = $paiement->commande;

        if (!$commande) {
            return response()->json([
                'message' => "Commande associée introuvable.",
            ], 404);
        }

        return DB::transaction(function () use ($paiement, $commande) {
            // 1️⃣ Total déjà encaissé AVANT cette tranche
            //    (on ignore toutes les tranches encore 'en_attente_caisse')
            $totalEncaisseAvant = Paiement::where('commande_id', $commande->id)
                ->where(function ($q) {
                    $q->whereNull('statut_paiement')
                      ->orWhere('statut_paiement', 'encaisse'); // si tu veux un statut explicite
                })
                ->sum('montant');

            // 2️⃣ Nouveau total encaissé APRÈS cette tranche
            $totalEncaisseApres = $totalEncaisseAvant + $paiement->montant;

            // 3️⃣ Nouveau reste dû
            $reste = max(0, $commande->total - $totalEncaisseApres);

            // 4️⃣ On marque la tranche comme encaissée côté caisse
            $paiement->update([
                'statut_paiement' => 'encaisse', // ou null si tu préfères
                'reste_du'        => $reste,
            ]);

            // 5️⃣ On met à jour la commande
            $nouveauStatut = $reste <= 0 ? 'payee' : 'partiellement_payee';

            $commande->update([
                'reste_du' => $reste,
                'statut'   => $nouveauStatut,
            ]);

            return response()->json([
                'paiement' => $paiement->fresh(),
                'commande' => $commande->fresh(),
            ], 200);
        });
    }

}
