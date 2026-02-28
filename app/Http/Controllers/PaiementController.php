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
        'type_paiement' => 'nullable|string',
    ]);
    $commande = Commande::findOrFail($commandeId);
    
    // 🔒 Vérifier montant exact envoyé par le responsable
    if ($commande->montant_a_encaisser === null) {
        return response()->json([
            'message' => 'Aucune tranche envoyée à la caisse pour cette commande.'
        ], 400);
    }

    if ((float) $request->input('montant') !== (float) $commande->montant_a_encaisser) {
        return response()->json([
            'message' => 'Le montant doit être exactement égal à la tranche envoyée.'
        ], 400);
    }
    
    if(!in_array($commande->statut, ['attente','partiellement_payee'])){
        return response()->json([
            'message' => 'Cette commande ne peut plus recevoir de paiement',
        ], 400);
    }
    
    // Pour les clients spéciaux, vérifier si un paiement existe déjà avec un type_paiement
    $commande->loadMissing('client');
    $isClientSpecial = optional($commande->client)->type_client === 'special';
    
    $totalDejaPaye = Paiement::where('commande_id', $commande->id)->sum('montant');
    $reste = max(0, $commande->total - ($totalDejaPaye + $request->input('montant')));

    # si le client n'est pas special il doit tout payer en une fois
    if(!$isClientSpecial && $reste != 0){
        return response()->json([
            'message' => 'Seul les clients specials peuvent payer par tranche.',
        ], 400);
    }

    // 🔥 TOUTE LA LOGIQUE CRITIQUE DANS UNE TRANSACTION
    return DB::transaction(function () use ($data, $commande, $request) {
        
        // 1️⃣ Créer le paiement SANS reste_du (on le calculera après)
        $paiement = Paiement::create([
            'commande_id' => $commande->id,
            'montant' => $data['montant'],
            'type_paiement' => $data['type_paiement'] ?? null,
            'date' => now(),
            'caissier_id' => Auth::user()->id ?? $commande->vendeur_id,
        ]);

        // 2️⃣ Calculer le total payé APRÈS création du paiement
        $totalDejaPayeApres = Paiement::where('commande_id', $commande->id)->sum('montant');
        $resteApres = max(0, $commande->total - $totalDejaPayeApres);

        // 3️⃣ Mettre à jour le paiement avec le vrai reste
        $paiement->update([
            'reste_du' => $resteApres
        ]);

        // 4️⃣ Mettre à jour le statut de la commande et du client
        if ($resteApres == 0) {
            $commande->update(['statut' => 'payee']);
            $client = $commande->client;
            $client->update([
                'statut' => 'paye',
                'solde' => 0,
                'dette' => 0,
                'total_paye' => $totalDejaPayeApres,
            ]);
        } else {
            $commande->update(['statut' => 'partiellement_payee']);
            $client = $commande->client;
            $client->update([
                'statut' => 'en_dette',
                'solde' => $resteApres,
                'dette' => $resteApres,
                'total_paye' => $totalDejaPayeApres,
            ]);
        }

        // 5️⃣ Nettoyer et mettre à jour la commande
        $commande->update([
            'montant_a_encaisser' => null,
            'caissier_id' => Auth::user()->id ?? $commande->vendeur_id
        ]);

        // 6️⃣ Créer la facture UNIQUEMENT si elle n'existe pas déjà
        $facture = Facture::firstOrCreate(
            ['commande_id' => $commande->id],
            [
                'total' => $commande->total,
                'mode_paiement' => $paiement->type_paiement,
                'date' => now(),
            ]
        );

        // 7️⃣ Mettre à jour le stock
        $commande->loadMissing(['details', 'vendeur']);

        // 🔥 Décrémenter le stock UNIQUEMENT au premier encaissement
        if ($totalDejaPayeApres == $data['montant']) {

            foreach ($commande->details as $detail) {

                // ⚠️ Sécuriser la récupération du stock boutique
                $stock_boutique = StockBoutique::where('produit_id', $detail->produit_id)->first();

                if (!$stock_boutique) {
                    Log::warning('Stock boutique introuvable pour le produit ' . $detail->produit_id);
                    continue;
                }

                $stock = TransfertEnAttente::where('produit_id', $stock_boutique->produit_id)->first();

                if ($stock) {

                    $nouvelleQuantite = max(0, $stock->quantite - $detail->quantite);

                    $stock->update([
                        'quantite' => $nouvelleQuantite
                    ]);

                    $stock_boutique->update([
                        'quantite' => $nouvelleQuantite
                    ]);

                    if ($nouvelleQuantite <= 0) {
                        try {
                            event(new StockRupture($stock->fresh()));
                        } catch (\Exception $e) {
                            Log::warning('Erreur lors de la diffusion de la rupture de stock: ' . $e->getMessage());
                        }
                    }
                }

                // ✅ Enregistrer la vente dans l'historique UNE SEULE FOIS
                HistoriqueVente::create([
                    'vendeur_id' => $commande->vendeur_id,
                    'produit_id' => $detail->produit_id,
                    'quantite' => $detail->quantite,
                    'prix_unitaire' => $detail->prix_unitaire ?? 0,
                    'montant' => ($detail->prix_unitaire ?? 0) * $detail->quantite,
                    'date' => now()
                ]);
            }
        }

        // 8️⃣ Diffuser les événements (hors transaction car non critiques)
        try {
            event(new PaiementCree($paiement));
            event(new FactureCree($facture));
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la diffusion des événements: ' . $e->getMessage());
        }

        // ✅ Retourner le paiement
        return $paiement;
    });
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
