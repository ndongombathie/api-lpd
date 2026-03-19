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
        # 1️⃣ Validation basique
        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => 'nullable|string',
        ]);

        $commande = Commande::findOrFail($commandeId);
        $commande->loadMissing('client');

        $isClientSpecial = optional($commande->client)->type_client === 'special';

        if (!in_array($commande->statut, ['attente','partiellement_payee'])) {
            return response()->json([
                'message' => 'Cette commande ne peut plus recevoir de paiement',
            ], 400);
        }

        # 🔥 TOUTE LA LOGIQUE CRITIQUE DANS TRANSACTION
        return DB::transaction(function () use ($data, $commande, $isClientSpecial) {

            # 🔒 2️⃣ Lock commande
            $commande = Commande::where('id', $commande->id)
                ->lockForUpdate()
                ->first();

            if (!$commande) {
                throw new \Exception("Commande introuvable.");
            }

            # 🔄 3️⃣ Recalcul total payé APRÈS lock
            $totalDejaPaye = Paiement::where('commande_id', $commande->id)
                ->lockForUpdate()
                ->sum('montant');

            $resteAvant = max(0, $commande->total - $totalDejaPaye);

            # ✅ 4️⃣ Validation montant sécurisée
            if ($isClientSpecial) {

                if ($commande->montant_a_encaisser === null) {
                    throw new \Exception("Aucune tranche envoyée à la caisse.");
                }

                if ($data['montant'] !== $commande->montant_a_encaisser) {
                    throw new \Exception("Le montant doit être égal à la tranche envoyée.");
                }

            } else {

                if ($data['montant'] !== $resteAvant) {
                    throw new \Exception("Le client doit payer le montant restant exact.");
                }
            }

            # 5️⃣ Création paiement
            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $data['montant'],
                'type_paiement' => $data['type_paiement'] ?? null,
                'date' => now(),
                'caissier_id' => Auth::user()->id ?? $commande->vendeur_id,
            ]);

            $paiement->somme_payees = $totalDejaPaye + $data['montant'];
            $paiement->save();

            # 🔥 6️⃣ PREMIER PAIEMENT → DÉCRÉMENTATION STOCK
            if ($totalDejaPaye == 0) {

                $commande->loadMissing('details');

                foreach ($commande->details as $detail) {
                    $transfert = TransfertEnAttente::where('id', $detail->transfert_en_attente_id)
                        ->where('status', 'valide')
                        ->lockForUpdate()
                        ->first();

                    if (!$transfert) {
                        throw new \Exception("Transfert valide introuvable.");
                    }

                    $produit = $transfert->produit;

                    if (!$produit) {
                        throw new \Exception("Produit introuvable.");
                    }

                    # 🔧 Calcul unités
                    if ($detail->mode_vente === 'gros') {
                        $unitesASortir = $detail->quantite * $produit->unite_carton;
                    } else {
                        $unitesASortir = $detail->quantite;
                    }

                    if ($transfert->quantite < $unitesASortir) {
                        throw new \Exception("Stock boutique insuffisant.");
                    }

                    # 🔻 Décrémentation
                    $transfert->quantite -= $unitesASortir;

                    # 🔁 Recalcul cartons
                    $transfert->nombre_carton = intdiv(
                        $transfert->quantite,
                        $produit->unite_carton
                    );

                    $transfert->save();

                    # 🚨 Rupture
                    if ($transfert->quantite <= 0) {
                        event(new StockRupture($transfert->produit));
                    }

                    # ⚠️ Sous seuil
                    if ($transfert->quantite <= $transfert->seuil) {
                        Log::warning("Produit sous seuil : ".$produit->nom);
                    }

                    HistoriqueVente::create([
                    'vendeur_id' => $commande->vendeur_id,
                    'produit_id' => $detail->produit_id,
                    'quantite' => $detail->quantite,
                    'transfert_en_attente_id' => $detail->transfert_en_attente_id,
                    'prix_unitaire' => $detail->prix_unitaire ?? 0,
                    'montant' => ($detail->prix_unitaire ?? 0) * $detail->quantite,
                    'date' => now()
                    ]);
                }
            }

            # 7️⃣ Recalcul reste après paiement
            $totalDejaPayeApres = Paiement::where('commande_id', $commande->id)->sum('montant');
            $resteApres = max(0, $commande->total - $totalDejaPayeApres);

            $paiement->update([
                'reste_du' => $resteApres
            ]);

            # 8️⃣ Mise à jour statut
            $client = $commande->client;

            if ($resteApres == 0) {
                $commande->update(['statut' => 'payee']);
                $client->update([
                    'statut' => 'paye',
                    'solde' => 0,
                    'dette' => 0,
                    'total_paye' => $totalDejaPayeApres,
                ]);
            } else {
                $commande->update(['statut' => 'partiellement_payee']);
                $client->update([
                    'statut' => 'en_dette',
                    'solde' => $resteApres,
                    'dette' => $resteApres,
                    'total_paye' => $totalDejaPayeApres,
                ]);
            }

            $commande->update([
                'montant_a_encaisser' => null,
                'caissier_id' => Auth::user()->id ?? $commande->vendeur_id
            ]);

            # 9️⃣ Facture
            $facture = Facture::firstOrCreate(
                ['commande_id' => $commande->id],
                [
                    'total' => $commande->total,
                    'mode_paiement' => $paiement->type_paiement,
                    'date' => now(),
                ]
            );

            try {
                event(new PaiementCree($paiement));
                event(new FactureCree($facture));
            } catch (\Exception $e) {
                Log::warning('Erreur events: '.$e->getMessage());
            }

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
    public function historiqueEncaissementsClient(Request $request, string $clientId)
    {
        try {

            $query = Paiement::with([
                'commande:id,numero,total,client_id',
                'caissier:id,nom,prenom'
            ])
            ->whereHas('commande', function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                ->whereNotIn('statut', ['annulee']);
            })
            ->orderByDesc('date');

            // 🔎 Filtre par date début
            if ($request->filled('date_debut')) {
                $query->whereDate('date', '>=', $request->date_debut);
            }

            // 🔎 Filtre par date fin
            if ($request->filled('date_fin')) {
                $query->whereDate('date', '<=', $request->date_fin);
            }

            // 🔎 Filtre recherche numéro commande
            if ($request->filled('search')) {
                $search = $request->search;

                $query->whereHas('commande', function ($q) use ($search) {
                    $q->where('numero', 'like', "%{$search}%");
                });
            }

            $paiements = $query->get()->map(function ($paiement) {

                $totalCommande = $paiement->commande->total ?? 0;

                // recalcul reste propre
                $totalPaye = Paiement::where('commande_id', $paiement->commande_id)
                    ->where('date', '<=', $paiement->date)
                    ->sum('montant');

                $reste = max(0, $totalCommande - $totalPaye);

                return [
                    'id' => $paiement->id,
                    'montant' => $paiement->montant,
                    'type_paiement' => $paiement->type_paiement,
                    'date' => $paiement->date,

                    'commande' => [
                        'id' => $paiement->commande->id ?? null,
                        'numero' => $paiement->commande->numero ?? null,
                        'total' => $totalCommande,
                        'reste' => $reste,
                    ],

                    'caissier' => [
                        'id' => $paiement->caissier->id ?? null,
                        'nom' => $paiement->caissier->nom ?? null,
                        'prenom' => $paiement->caissier->prenom ?? null,
                    ]
                ];
            });

            return response()->json($paiements);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur récupération historique encaissements',
                'error' => $th->getMessage(),
            ], 500);
        }
    }
}
