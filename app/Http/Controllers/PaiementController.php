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
        $commande = Commande::findOrFail($commandeId);
        return Paiement::where('commande_id', $commande->id)->orderBy('date')->get();
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
                ->whereDate('paiements.date', $date)
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
                'caissier_id' => Auth::user()->id ?? $commande->vendeur_id, // Fallback to vendeur if no auth user
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
