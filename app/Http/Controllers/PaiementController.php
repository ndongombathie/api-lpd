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
        
        // Pour les clients spéciaux, vérifier si un paiement existe déjà avec un type_paiement
        $commande->loadMissing('client');
        $isClientSpecial = optional($commande->client)->type_client === 'special';
        
        // Récupérer tous les paiements existants pour cette commande
        $paiementsExistants = Paiement::where('commande_id', $commande->id)->get();
        
        // Si client spécial et paiements existants, utiliser le type_paiement du premier paiement
        $typePaiementParDefaut = null;
        if ($isClientSpecial && $paiementsExistants->isNotEmpty()) {
            // Prendre le type_paiement du premier paiement (créé par le responsable)
            $typePaiementParDefaut = $paiementsExistants->first()->type_paiement;
        }
        
        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => $isClientSpecial && $typePaiementParDefaut 
                ? 'nullable|string' 
                : 'required|string',
        ]);
        
        // Utiliser le type_paiement du paiement existant pour les clients spéciaux si non fourni ou vide
        if ($isClientSpecial && $typePaiementParDefaut) {
            if (empty($data['type_paiement']) || $data['type_paiement'] === '' || $data['type_paiement'] === null) {
                $data['type_paiement'] = $typePaiementParDefaut;
            }
        }
        
        // Validation finale : le type_paiement doit être présent
        if (empty($data['type_paiement']) || $data['type_paiement'] === null) {
            return response()->json([
                'message' => 'Le type de paiement est requis. Pour les clients spéciaux, le responsable doit définir le moyen de paiement.',
                'error' => 'type_paiement_required'
            ], 422);
        }


            $totalPaye = Paiement::where('commande_id', $commande->id)->sum('montant');
            $reste = max(0, $commande->total - $totalPaye - $data['montant']);

            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $data['montant'],
                'type_paiement' => $data['type_paiement'],
                'date' => now(),
                'reste_du' => $reste,
            ]);

            // Diffuser l'événement de paiement (sans bloquer si Reverb n'est pas disponible)
            try {
            event(new PaiementCree($paiement));
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas l'opération
                \Log::warning('Erreur lors de la diffusion du paiement: ' . $e->getMessage());
            }

            // Traiter la finalisation de la commande (mise à jour du statut, stock, etc.)
            // Même en cas d'erreur, on retourne le paiement car il est déjà créé
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
                
                // Traiter chaque détail avec gestion d'erreur individuelle
                foreach ($commande->details as $detail) {
                    try {
                    // Décrémenter le stock de la boutique pour chaque produit
                    $stock = Transfer::where('boutique_id', $boutiqueId)
                        ->where('produit_id', $detail->produit_id)
                        ->first();
                    if ($stock) {
                        $stock->update(['quantite' => max(0, $stock->quantite - $detail->quantite)]);
                        if ($stock->quantite <= 0) {
                                try {
                            event(new StockRupture($stock->fresh()));
                                } catch (\Exception $e) {
                                    \Log::warning('Erreur lors de la diffusion de la rupture de stock: ' . $e->getMessage());
                                }
                        }
                    }

                    // Enregistrer la vente dans l'historique
                    HistoriqueVente::create([
                        'vendeur_id' => $commande->vendeur_id,
                        'produit_id' => $detail->produit_id,
                        'quantite' => $detail->quantite,
                        'prix_unitaire' => $detail->prix_unitaire ?? 0,
                        // Montant pour ce produit spécifique
                        'montant' => ($detail->prix_unitaire ?? 0) * $detail->quantite,
                    ]);

                    MouvementStock::create([
                        'source' => 'boutique:' . $boutiqueId,
                        'destination' => null,
                        'produit_id' => $detail->produit_id,
                        'quantite' => $detail->quantite,
                            'type' => 'sortie', // 'sortie' pour une vente (sortie de stock)
                        'date' => now(),
                    ]);
                    } catch (\Exception $e) {
                        // Log l'erreur pour ce produit mais continue avec les autres
                        \Log::error('Erreur lors de la mise à jour du stock pour le produit ' . $detail->produit_id . ': ' . $e->getMessage());
                        // On continue avec les autres produits
                    }
                }

                // Diffuser l'événement de facture
                try {
                event(new FactureCree($facture));
                } catch (\Exception $e) {
                    \Log::warning('Erreur lors de la diffusion de la facture: ' . $e->getMessage());
                }
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
