<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Facture;
use App\Models\MouvementStock;
use App\Events\PaiementCree;
use App\Events\FactureCree;
use App\Events\StockRupture;
use Illuminate\Http\Request;
use App\Models\Transfer;
use App\Models\HistoriqueVente;

class PaiementController extends Controller
{
    protected $historique;

    public function __construct(HistoriqueVenteController $historique)
    {
        $this->historique = $historique;
    }

    // =========================================================
    // 📄 Liste des paiements d'une commande
    // =========================================================
    public function index(string $commandeId)
    {
        return Paiement::where('commande_id', $commandeId)
            ->orderBy('date')
            ->get();
    }

    // =========================================================
    // 💰 Paiement par tranches (acomptes + solde)
    // =========================================================
    public function store(Request $request, string $commandeId)
    {
        $commande = Commande::with(['details', 'vendeur'])->findOrFail($commandeId);

        // 🔒 VERROU ABSOLU : une commande annulée est intouchable
        if ($commande->statut === 'annulee') {
            return response()->json([
                'message' => 'Commande annulée — aucun paiement ni recalcul autorisé.'
            ], 409);
        }

        // 🔒 Une commande soldée est aussi verrouillée
        if ($commande->statut === 'soldee') {
            return response()->json([
                'message' => 'Cette commande est déjà soldée.'
            ], 409);
        }

        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => 'required|string',
        ]);

        // 💰 Total déjà payé
        $dejaPaye = $commande->montantPaye();
        $nouveauTotal = $dejaPaye + $data['montant'];

        // ❌ Interdire de dépasser le total
        if ($nouveauTotal > $commande->total) {
            return response()->json([
                'message' => 'Le montant dépasse le total de la commande.'
            ], 422);
        }

        // 💾 Enregistrer le paiement
        $paiement = Paiement::create([
            'commande_id' => $commande->id,
            'montant' => $data['montant'],
            'type_paiement' => $data['type_paiement'],
            'date' => now(),
            'reste_du' => max(0, $commande->total - $nouveauTotal),
        ]);

        event(new PaiementCree($paiement));

        // ===========================
        // 🧠 Moteur officiel du statut
        // ===========================
        $ancienStatut = $commande->statut;
        $commande->recalcStatut();   // ← seule source de vérité

        // ===========================
        // 🧾 Facture + stock uniquement
        // quand on passe à SOLDEE
        // ===========================
        if ($ancienStatut !== 'soldee' && $commande->statut === 'soldee') {

            // 🧾 Créer la facture
            $facture = Facture::create([
                'commande_id' => $commande->id,
                'total' => $commande->total,
                'mode_paiement' => $data['type_paiement'],
                'date' => now(),
            ]);

            // 📦 Mise à jour du stock
            $boutiqueId = optional($commande->vendeur)->boutique_id;

            foreach ($commande->details as $detail) {

                $stock = Transfer::where('boutique_id', $boutiqueId)
                    ->where('produit_id', $detail->produit_id)
                    ->first();

                if ($stock) {
                    $stock->update([
                        'quantite' => max(0, $stock->quantite - $detail->quantite)
                    ]);

                    if ($stock->quantite <= 0) {
                        event(new StockRupture($stock->fresh()));
                    }
                }

                // 🧾 Historique de vente
                $this->historique->store(new Request([
                    'vendeur_id' => $commande->vendeur_id,
                    'produit_id' => $detail->produit_id,
                    'quantite' => $detail->quantite,
                    'montant' => $detail->prix_unitaire * $detail->quantite,
                ]));

                // 📦 Mouvement de stock
                MouvementStock::create([
                    'source' => 'boutique:' . $boutiqueId,
                    'destination' => null,
                    'produit_id' => $detail->produit_id,
                    'quantite' => $detail->quantite,
                    'type' => 'vente',
                    'date' => now(),
                ]);
            }

            event(new FactureCree($facture));
        }

        return response()->json($paiement, 201);
    }
}
