<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Facture;
use App\Models\DetailCommande;
use App\Models\StockBoutique;
use App\Models\MouvementStock;
use App\Events\PaiementCree;
use App\Events\FactureCree;
use App\Events\StockRupture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transfer;
use App\Models\HistoriqueVente;

class PaiementController extends Controller
{
    protected $historique;
    public function __construct(HistoriqueVenteController $historique) {
      $this->historique=$historique;
    }

    public function index(string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);
        return Paiement::where('commande_id', $commande->id)->orderBy('date')->get();
    }




    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);
        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => 'required|string',
        ]);


            $totalPaye = Paiement::where('commande_id', $commande->id)->sum('montant');
            $reste = max(0, $commande->total - $totalPaye - $data['montant']);

            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $data['montant'],
                'type_paiement' => $data['type_paiement'],
                'date' => now(),
                'reste_du' => $reste,
            ]);

            // Diffuser l'événement de paiement
            event(new PaiementCree($paiement));

            if ($reste <= 0) {
                $commande->update(['statut' => 'payee']);

                // Créer la facture
                $facture = Facture::create([
                    'commande_id' => $commande->id,
                    'total' => $commande->total,
                    'mode_paiement' => $paiement->type_paiement,
                    'date' => now(),
                ]);

                // Mettre à jour le stock de la boutique et enregistrer le mouvement
                $commande->loadMissing(['details', 'vendeur']);
                $boutiqueId = optional($commande->vendeur)->boutique_id;
                foreach ($commande->details as $detail) {
                    // Décrémenter le stock de la boutique pour chaque produit
                    $stock = Transfer::where('boutique_id', $boutiqueId)
                        ->where('produit_id', $detail->produit_id)
                        ->first();
                    if ($stock) {
                        $stock->update(['quantite' => max(0, $stock->quantite - $detail->quantite)]);
                        if ($stock->quantite <= 0) {
                            event(new StockRupture($stock->fresh()));
                        }
                    }

                    // Enregistrer la vente dans l'historique
                    $this->historique->store(
                        new Request([
                            'vendeur_id' => $commande->vendeur_id,
                            'produit_id' => $detail->produit_id,
                            'quantite' => $detail->quantite,
                            'montant' => $detail->montant,
                        ])
                    );

                    MouvementStock::create([
                        'source' => 'boutique:' . $boutiqueId,
                        'destination' => null,
                        'produit_id' => $detail->produit_id,
                        'quantite' => $detail->quantite,
                        'type' => 'vente',
                        'date' => now(),
                    ]);
                }

                // Diffuser l'événement de facture
                event(new FactureCree($facture));
            }
            return $paiement;
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Paiement::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort(405);
    }
}
