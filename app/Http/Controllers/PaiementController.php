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




    public function rapportJournalier(Request $request)
    {
        $date = $request->input('date') ?? date('Y-m-d');

        try {
            // Paiements grouped by cashier (using caissier_id)
            $paiements = DB::table('paiements')
                ->join('users', 'paiements.caissier_id', '=', 'users.id')
                ->select(
                    'users.id as caissier_id',
                    'users.nom',
                    'users.prenom',
                    DB::raw('COUNT(paiements.id) as nombre_paiement'),
                    DB::raw('SUM(paiements.montant) as valeur_total_paiement')
                )
                //->whereDate('paiements.date', $date)
                ->groupBy('users.id', 'users.nom', 'users.prenom')
                ->get();

            // Decaissements grouped by caissier
            $decaissements = DB::table('decaissements')
                ->select(
                    'caissier_id',
                    DB::raw('SUM(montant) as total_decaissement')
                )
                ->whereDate('date', $date)
                ->whereNotNull('caissier_id')
                ->groupBy('caissier_id')
                ->get()
                ->keyBy('caissier_id');

            // Get all unique caissier IDs involved
            $caissierIds = $paiements->pluck('caissier_id')->merge($decaissements->keys())->unique();

            $rapport = [];
            foreach ($caissierIds as $id) {
                $p = $paiements->firstWhere('caissier_id', $id);
                $d = $decaissements->get($id);

                if ($p) {
                    $nom = $p->nom;
                    $prenom = $p->prenom;
                } else {
                    $user = DB::table('users')->where('id', $id)->select('nom', 'prenom')->first();
                    $nom = $user ? $user->nom : 'Inconnu';
                    $prenom = $user ? $user->prenom : '';
                }

                $rapport[] = [
                    'caissier_nom' => $nom . ' ' . $prenom,
                    'date_journalier' => $date,
                    'fond_de_caisse' => 0, // Placeholder as requested
                    'nombre_paiement' => $p ? $p->nombre_paiement : 0,
                    'valeur_total_paiement' => $p ? $p->valeur_total_paiement : 0,
                    'total_decaissement' => $d ? $d->total_decaissement : 0,
                    'caisse_final'=> $p->valeur_total_paiement - ($d->total_decaissement + 0),
                ];
            }

            return response()->json($rapport);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $commandeId)
    {
        # les validations

        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => 'required|string',
        ]);

        $commande = Commande::findOrFail($commandeId);
        if($commande->statut !== 'attente'){
            return response()->json([
                'message' => 'Seules les commandes en attente peuvent être payées',
            ], 400);
            abort(400, 'Seules les commandes en attente peuvent être payées');
        }
        // Pour les clients spéciaux, vérifier si un paiement existe déjà avec un type_paiement
        $commande->loadMissing('client');
        $isClientSpecial = optional($commande->client)->type_client === 'special';
        //dd($isClientSpecial);
        $reste = $commande->total - $request->input('montant') > 0 ? $commande->total - $request->input('montant') : 0 ;

        # si le client n'est pas special il doit tout payer en une fois
        if(!$isClientSpecial && $reste != 0){
            return response()->json([
                'message' => 'Seul les clients specials peuvent payer par tranche.',
            ], 400);
            abort(400, 'Seul les clients spéciaux peuvent payer par tranche.');
        }


            // Diffuser l'événement de paiement (sans bloquer si Reverb n'est pas disponible)


            // Traiter la finalisation de la commande (mise à jour du statut, stock, etc.)
            // Même en cas d'erreur, on retourne le paiement car il est déjà créé
            if ($reste == 0) {
                $commande->update(['statut' => 'payee']);
                #recuperer le client et changer son statut en paye
                $client = $commande->client;
                $client->update(['statut' => 'paye']);
                $client->update([
                'solde' => 0,
                'dette' => 0,
                'total_paye' => $request->input('montant'),
                ]);
            }
            else{
                $commande->update(['statut' => 'partiellement_payee']);
                #recuperer le client et changer son statut en en_dette
                $client = $commande->client;
                $client->update(['statut' => 'en_dette']);
                $client->update([
                'solde' => $reste,
                'dette' => $reste,
                'total_paye' => Paiement::where('commande_id', $commande->id)->sum('montant'),
                ]);
            }

            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $data['montant'],
                'type_paiement' => $data['type_paiement'],
                'date' => now(),
                'reste_du' => $reste,
                'caissier_id' => Auth::user()->id ?? $commande->vendeur_id, // Fallback to vendeur if no auth user
            ]);

            try {
            event(new PaiementCree($paiement));
            } catch (\Exception $e) {
                // Log l'errTransfereeur mais ne bloque pas l'opération
                Log::warning('Erreur lors de la diffusion du paiement: ' . $e->getMessage());
            }

            $commande->update(['caissier_id' => Auth::user()->id]);

                // Créer la facture
                $facture = Facture::create([
                    'commande_id' => $commande->id,
                    'total' => $commande->total,
                    'mode_paiement' => $paiement->type_paiement,
                    'date' => now(),
                ]);

                // Mettre à jour le stock de la boutique et enregistrer le mouvement
                $commande->loadMissing(['details', 'vendeur']);

                // Traiter chaque détail avec gestion d'erreur individuelle
                foreach ($commande->details as $detail) {
                    try {

                    // Décrémenter le stock de la boutique pour chaque produit
                    $stock_boutique=StockBoutique::where('produit_id', $detail->produit_id)->first();
                    $stock = TransfertEnAttente::where('produit_id', $stock_boutique->produit_id)->first();

                    if ($stock) {
                        $stock->update(['quantite' => max(0, $stock->quantite - $detail->quantite)]);
                        $stock_boutique->update(['quantite' => max(0, $stock->quantite - $detail->quantite)]);
                        if ($stock->quantite <= 0) {
                                try {
                            event(new StockRupture($stock->fresh()));
                                } catch (\Exception $e) {
                                    Log::warning('Erreur lors de la diffusion de la rupture de stock: ' . $e->getMessage());
                                }
                        }
                    }

                    // Enregistrer la vente dans l'historique
                    HistoriqueVente::create([
                        'vendeur_id' => $commande->vendeur_id,
                        'produit_id' => $detail->produit_id,
                        'quantite' => $detail->quantite,
                        'prix_unitaire' => $detail->prix_unitaire ?? 0,
                        'montant' => ($detail->prix_unitaire ?? 0) * $detail->quantite,
                        'date' => now()
                                        ]);

                    } catch (\Exception $e) {
                        // Log l'erreur pour ce produit mais continue avec les autres
                        Log::error('Erreur lors de la mise à jour du stock pour le produit ' . $detail->produit_id . ': ' . $e->getMessage());
                        // On continue avec les autres produits
                    }
                }
                // Diffuser l'événement de facture
                try {
                event(new FactureCree($facture));
                } catch (\Exception $e) {
                    Log::warning('Erreur lors de la diffusion de la facture: ' . $e->getMessage());
                }

            return $paiement;
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

        $montantPaye = $request->input('montant');
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
