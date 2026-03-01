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
use App\Models\TransfertEnAttente;
use App\Models\HistoriqueVente;
use Illuminate\Support\Facades\Log;


use App\Models\Decaissement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PaiementController extends Controller
{
    protected $historique;
    public function __construct(HistoriqueVenteController $historique) {
      $this->historique=$historique;
    }

    public function index(string $commandeId)
    {
        try {
            $commande = Commande::findOrFail($commandeId);
            return Paiement::where('commande_id', $commande->id)->orderBy('date')->get();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }




    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $commandeId)
    {
        $data = $request->validate([
            'type_paiement' => 'required|string',
        ]);

        $commande = Commande::findOrFail($commandeId);
        if ($commande->statut !== 'attente') {
            return response()->json([
                'message' => 'Cette commande est en cours de traitement, vous ne pouvez pas payer.',
            ], 400);
        }

        $isClientSpecial = $this->isClientSpecial($commande);
        if (!$isClientSpecial) {
            $commande->update(['premiere_tranche' => $commande->total]);
        }

        $reste = $this->computeReste($commande);

        if (!$isClientSpecial && $reste != 0) {
            return response()->json([
                'message' => 'Seul les clients specials peuvent payer par tranche.',
            ], 400);
        }

        if ($reste == 0) {
            $this->finalizeFullPayment($commande);
        } else {

            if($this->getMontantPaye($commande)>=$commande->total){
                $this->updateClientPourDette($commande, 0, $commande->total,'paye');
                $commande->statut = 'payee';
                $commande->premiere_tranche = 0;
                $commande->save();
                return response()->json([
                    'message' => 'La commande a été payée entièrement.',
                ], 200);
                abort(400, 'la commande a été payée entièrement.');
            }

            $dernierPaiement = $this->getDernierPaiement($commande);
            $montantPaye = $this->getMontantPaye($commande);
            $commande->statut = 'partiellement_payee';


            if ($montantPaye > $commande->total) {
                return response()->json([
                    'message' => 'Le montant payé excède le total de la commande.',
                ], 400);
            }

            if (!$dernierPaiement) {
                $commande->save();
                $this->updateClientPourDette($commande, $reste, $commande->premiere_tranche);
            } else {
                $paiement = $this->createPaiementForCommande(
                    $commande,
                    $commande->premiere_tranche,
                    $data['type_paiement'],
                    $commande->total - ($montantPaye + $commande->premiere_tranche),
                    $this->getMontantPaye($commande)
                );

                $commande->save();
                $this->updateClientPourDette(
                    $commande,
                    $commande->total - ($montantPaye + $commande->premiere_tranche),
                    $this->getMontantPaye($commande)
                );
                $commande->update(['caissier_id' => Auth::user()->id]);

                return response()->json([
                    'message' => 'Paiement effectué avec succès.',
                    'paiement' => $paiement,
                ], 201);
            }
        }

        $paiement = $this->createPaiementForCommande(
            $commande,
            $commande->premiere_tranche,
            $data['type_paiement'],
            $reste,
            $commande->premiere_tranche
        );

        $commande->update(['caissier_id' => Auth::user()->id]);

        $facture = $this->createFactureForCommande($commande, $paiement);
        $this->traiterStockEtHistorique($commande);

        return $paiement;
    }

    private function isClientSpecial(Commande $commande): bool
    {
        $commande->loadMissing('client');
        return optional($commande->client)->type_client === 'special';
    }

    private function computeReste(Commande $commande): float
    {
        $reste = $commande->total - $commande->premiere_tranche;
        return $reste > 0 ? $reste : 0;
    }

    private function finalizeFullPayment(Commande $commande): void
    {
        $commande->update(['statut' => 'payee']);
        $client = $commande->client;
        $client->update(['statut' => 'paye']);
        $client->update([
            'solde' => 0,
            'dette' => 0,
            'total_paye' => $commande->premiere_tranche,
        ]);
        $client->save();
    }

    private function getDernierPaiement(Commande $commande): ?Paiement
    {
        return Paiement::where('commande_id', $commande->id)->orderByDesc('date')->first();
    }

    private function getMontantPaye(Commande $commande): float
    {
        return (float) Paiement::where('commande_id', $commande->id)->sum('montant');
    }

    private function createPaiementForCommande(Commande $commande, float $montant, string $typePaiement, float $resteDu, ?float $sommePayees = null): Paiement
    {
        try {
            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $montant,
                'type_paiement' => $typePaiement,
                'date' => now(),
                'reste_du' => $resteDu,
                'caissier_id' => Auth::user()->id ?? $commande->vendeur_id,
            ]);
            $paiement->somme_payees = $sommePayees ?? $montant;
            $paiement->save();
            event(new PaiementCree($paiement));
            return $paiement;
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la diffusion du paiement: ' . $e->getMessage());
            throw $e;
        }
    }

    private function updateClientPourDette(Commande $commande, float $solde, ?float $totalPaye = null,string $statut = 'en_dette'): void
    {
        $client = $commande->client;
        $client->statut = $statut;
        $client->solde = $solde;
        $client->dette = $solde;
        if ($totalPaye !== null) {
            $client->total_paye = $totalPaye;
        }
        $client->save();
    }

    private function createFactureForCommande(Commande $commande, Paiement $paiement): Facture
    {
        $facture = Facture::create([
            'commande_id' => $commande->id,
            'total' => $commande->total,
            'mode_paiement' => $paiement->type_paiement,
            'date' => now(),
        ]);
        try {
            event(new FactureCree($facture));
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la diffusion de la facture: ' . $e->getMessage());
        }
        return $facture;
    }

    private function traiterStockEtHistorique(Commande $commande): void
    {
        $commande->loadMissing(['details', 'vendeur']);
        try {
            foreach ($commande->details as $detail) {
                $stockBoutique = StockBoutique::where('produit_id', $detail->produit_id)->first();
                $stock = $stockBoutique ? TransfertEnAttente::where('produit_id', $stockBoutique->produit_id)->first() : null;
                if ($stock) {
                    $stock->update(['quantite' => max(0, $stock->quantite - $detail->quantite)]);
                    if ($stock->quantite <= 0) {
                        try {
                            event(new StockRupture($stock->fresh()));
                        } catch (\Exception $e) {
                            Log::warning('Erreur lors de la diffusion de la rupture de stock: ' . $e->getMessage());
                        }
                    }
                }
            }

            HistoriqueVente::create([
                'vendeur_id' => $commande->vendeur_id,
                'produit_id' => $detail->produit_id,
                'quantite' => $detail->quantite,
                'prix_unitaire' => $detail->prix_unitaire ?? 0,
                'montant' => ($detail->prix_unitaire ?? 0) * $detail->quantite,
                'date' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du stock pour le produit ' . $detail->produit_id . ': ' . $e->getMessage());
        }
    }

   #payer par tranche pour une commande donnee jusqu'a atteindre le montant total de la commande
    public function payementParTranche(Request $request, string $commandeId){
        # les validtions
        $request->validate([
            'montant' => 'required|numeric|min:0',
            'type_paiement' => 'required|string',
        ]);

        $commande = Commande::findOrFail($commandeId);
        if($commande->statut !== 'partiellement_payee'){
            return response()->json([
                'message' => 'Seules les commandes partiellement payées peuvent être payées',
            ], 400);
        }

        $montantPaye = $commande->premiere_tranche;
        if($montantPaye > $commande->total){
            return response()->json([
                'message' => 'Le montant payé ne peut pas dépasser le montant total de la commande',
            ], 400);
        }

        if($commande->total == Paiement::where('commande_id', $commande->id)->sum('montant')){
            $commande->update(['statut' => 'payee']);
            #recuperer le client et changer son statut en paye
            $client = $commande->client;
            $client->update(['statut' => 'paye']);
            $client->update([
                'solde' => 0,
                'dette' => 0,
                'total_paye' => Paiement::where('commande_id', $commande->id)->sum('montant'),
            ]);

            return response()->json([
                'message' => 'La commande a été payée entièrement',
            ], 400);
        }
        // Créer le paiement

        #le dernier montant payer pour la commande
        $dernierPaiement = Paiement::where('commande_id', $commande->id)->latest()->first();

        $paiement = Paiement::create([
            'caissier_id' => Auth::user()->id,
            'commande_id' => $commande->id,
            'montant' => $montantPaye,
            'type_paiement' => $request->input('type_paiement'),
            'reste_du' => $commande->total - ($montantPaye + $dernierPaiement->montant),
        ]);

        $commande->update(['statut' => 'partiellement_payee']);
        $client = $commande->client;
        $client->update(['statut' => 'en_dette']);
        $client->update([
            'solde' => $commande->total - ($montantPaye + $dernierPaiement->montant),
            'total_paye' => Paiement::where('commande_id', $commande->id)->sum('montant'),
        ]);

        // Mettre à jour le reste du montant à payer pour la commande
        return $paiement;
    }

    #la liste des paiement associer a une commande
    public function listePaiements(string $commandeId){
        $commande = Commande::findOrFail($commandeId);
        $paiements = Paiement::where('commande_id', $commande->id);

        return response()->json($paiements->paginate(10));
    }

    #•	Reste total à encaisser.
    public function resteTotalEncaisser(){
        try {

            $montantTotal = Commande::where('statut', '!=', 'annulee')
                ->sum('total');

            $totalPaiements = Paiement::whereHas('commande', function ($q) {
                $q->whereNotIn('statut', ['annulee', 'attente']);
            })->sum('montant');

            $reste = $montantTotal - $totalPaiements;

            return response()->json($reste);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur reste total',
                'error' => $th->getMessage(),
            ], 500);
        }
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

    #la somme total des paiements
    public function sommeTotalPaiements(){
        try {
            $totalPaiements = Paiement::whereHas('commande', function ($q) {
                $q->whereNotIn('statut', ['annulee', 'attente']);
            })->sum('montant');

            return response()->json($totalPaiements);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur total paiements',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
