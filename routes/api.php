<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoutiqueController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HistoriqueVenteController;
use App\Http\Controllers\TransfertEnAttenteController;
use App\Http\Controllers\DecaissementController;
use App\Http\Controllers\CaissierDashboardController;
use App\Http\Controllers\CaissierCaisseJournalController;
use App\Http\Controllers\EnregistrerVersementController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FondCaisseController;
use App\Http\Controllers\HistoriqueActionController;
use App\Http\Controllers\MouvementSockController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\Api\RapportController;


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});

/*
|--------------------------------------------------------------------------
| API PROTÉGÉE
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ---------------- PROFIL ----------------
    Route::get('montant-total-boutique', [BoutiqueController::class, 'montantTotalBoutique']);
    Route::get('benefice-boutique', [BoutiqueController::class, 'BeneficeBoutique']);
    Route::get('montant-total-ventes-today', [BoutiqueController::class, 'montantTotalVentesToday']);



    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('mon-profil', [AuthController::class, 'monProfil']);
    Route::put('mon-profil', [AuthController::class, 'updateProfil']);
    Route::put('change-password', [AuthController::class, 'changePassword']);

    // ---------------- DASHBOARD ----------------
    Route::get('montant-total-boutique', [BoutiqueController::class, 'montantTotalBoutique']);
    Route::get('benefice-boutique', [BoutiqueController::class, 'BeneficeBoutique']);

    // ---------------- RESSOURCES ----------------
    Route::apiResource('categories', CategorieController::class);
    Route::apiResource('produits', ProduitController::class);
    Route::apiResource('clients', ClientController::class);
    Route::apiResource('fournisseurs', FournisseurController::class);
    Route::apiResource('utilisateurs', UserController::class);
    Route::get('vendeurs', [UserController::class, 'vendeursStats']);
    Route::get('/caissiers', [UserController::class, 'caissiersStats']);
    Route::get('caissiers/all', [UserController::class, 'allCaissiers']);
    Route::get('nombre-fournisseurs', [FournisseurController::class, 'nombreFournisseur']);



    // ---------------- CLIENTS ----------------
    Route::get('clients/{client}/paiements-tranches', [ClientController::class, 'paiementsTranches']);
    Route::get('clients/{client}/paiements', [ClientController::class, 'paiementsTranches']);
    Route::get('clients/dette', [ClientController::class, 'clientsDette']);



    // ---------------- COMMANDES ----------------
    //Route::apiResource('commandes', CommandeController::class);
    Route::get('commandes/pending', [CommandeController::class, 'pending']);
    Route::post('commandes/{commande}/valider', [CommandeController::class, 'valider']);
    Route::post('commandes/{commande}/annuler', [CommandeController::class, 'annuler']);

    // ----- LIGNES (commande_lignes) -----
    Route::get('commandes/{commande}/lignes', [CommandeController::class, 'lignes']);
    Route::post('commandes/{commande}/lignes', [CommandeController::class, 'storeLigne']);
    Route::put('commandes/lignes/{ligne}', [CommandeController::class, 'updateLigne']);
    Route::delete('commandes/lignes/{ligne}', [CommandeController::class, 'deleteLigne']);

    // ---------------- PAIEMENTS ----------------
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);
    //Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::put('paiements/{paiement}', [PaiementController::class, 'update']);
    Route::delete('paiements/{paiement}', [PaiementController::class, 'destroy']);
    #paiement par tranche
    Route::post('commandes/{commande}/paiements-tranches', [PaiementController::class, 'payementParTranche']);
    #la liste des paiement associer a une commande
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'listePaiements']);



    // ---------------- HISTORIQUES ----------------
    Route::get('historique-ventes', [HistoriqueVenteController::class, 'index']);
    Route::get('total-vente-par-jour', [HistoriqueVenteController::class, 'totalParJour']);
    Route::get('inventaires-boutique', [HistoriqueVenteController::class, 'inventaireBoutique']);


    /*
    |--------------------------------------------------------------------------
    | RAPPORTS / AUDIT LOGS
    |--------------------------------------------------------------------------
    | Journal d'activité du responsable
    | Fournisseurs & Clients spéciaux
    */
    Route::prefix('rapports')->group(function () {

        // Journal fournisseurs
        Route::get('fournisseurs', [RapportController::class, 'logsFournisseurs']);

        // Journal clients spéciaux
        Route::get('clients', [RapportController::class, 'logsClients']);

    });


    // ---------------- STOCK ----------------
    //Route::get('stocks', [StockController::class, 'index']);
    Route::get('stocks/ruptures', [StockController::class, 'ruptures']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);
    Route::post('stocks/reapprovisionner', [StockController::class, 'reapprovisionner']);

    Route::get('produits-ruptures', [ProduitController::class, 'produits_en_rupture']);

    // ---------------- TRANSFERTS ----------------
    Route::get('transfers/boutique/{boutique_id}', [TransfertEnAttenteController::class, 'produitsByBoutique']);
    Route::get('transfers-annuler', [TransfertEnAttenteController::class, 'getTransferAnnuler']);
    Route::get('liste-transfers-annuler',[TransfertEnAttenteController::class,'transfertAnnuler']);

    Route::get('transfers/valide', [TransfertEnAttenteController::class, 'getTransferValide']);
    Route::get('produits-transfer', [TransfertEnAttenteController::class, 'index']);
    Route::get('all-produits-transfer', [TransfertEnAttenteController::class, 'alltransfert']);
    Route::put('valider-produits-transfer', [TransfertEnAttenteController::class, 'valideTransfer']);
    Route::put('annuler-produits-transfer', [StockController::class, 'annulerTransfert']);

    Route::get('produits-disponibles-boutique', [TransfertEnAttenteController::class, 'produitsDisponibles']);
    #dramé
    Route::get('nombre-produits-total', [TransfertEnAttenteController::class, 'nombreProduits']);
    Route::get('quantite-totale-produit', [TransfertEnAttenteController::class, 'quantiteTotaleProduit']);
    Route::get('produits-sous-seuil', [TransfertEnAttenteController::class, 'produitsSousSeuil']);
    Route::get('produits-rupture', [TransfertEnAttenteController::class, 'produitsRupture']);
    Route::get('montant-total-stock', [TransfertEnAttenteController::class, 'MontantTotalStock']);
    #dramé
    Route::get('produits-controle-boutique', [TransfertEnAttenteController::class, 'produitsControleBoutique']);
    Route::get('produits-controle-depots', [TransfertEnAttenteController::class, 'produitsControleDepots']);


    // ---------------- DECAISSEMENTS ----------------

    Route::get('decaissements/export', [DecaissementController::class, 'exportAll']);

    # Gestion des historiques de vente
    Route::get('historique-ventes', [HistoriqueVenteController::class, 'index']);
    Route::get('total-vente-par-jour', [HistoriqueVenteController::class, 'totalParJour']);
    Route::get('inventaires-boutique', [HistoriqueVenteController::class, 'inventaireBoutique']);
    #Nombre total de ventes par vendeur et les info du vendeur
    Route::get('total-ventes-par-vendeur', [HistoriqueVenteController::class, 'totalVentesParVendeur']);

    #impression de la facture
    Route::get('factures/{id}', [FactureController::class, 'show']);





    Route::get('stocks', [StockController::class, 'index']);
    Route::apiResource('decaissements', DecaissementController::class);
    Route::get('decaissements-attente', [DecaissementController::class, 'getDecaissementsEnAttente']);
    Route::put('decaissements/{decaissement}/statut', [DecaissementController::class, 'updateStatusDecaissement']);
    Route::get('decaissements-all', [DecaissementController::class, 'getDecaissements']);

    // Dashboard caissier (optimisé côté backend)
    Route::get('caissier/dashboard/stats', [CaissierDashboardController::class, 'stats']);
    Route::get('caissier/dashboard/ventes-par-moyen', [CaissierDashboardController::class, 'ventesParMoyen']);
    Route::get('caissier/dashboard/ventes-par-heure', [CaissierDashboardController::class, 'ventesParHeure']);

    // Rapports journaliers de caisse (caissier + comptable)
    Route::get('caissier/caisses-journal', [CaissierCaisseJournalController::class, 'index']);
    Route::get('caissier/caisses-journals', [CaissierCaisseJournalController::class, 'all']);
    Route::get('caissier/caisses-journal/{date}', [CaissierCaisseJournalController::class, 'show']);
    Route::post('caissier/caisses-journal', [CaissierCaisseJournalController::class, 'store']);
    Route::put('caissier/caisses-journal/{date}/cloture', [CaissierCaisseJournalController::class, 'cloture']);
    Route::get('caissier/caisses-journal-total-encaissement', [CaissierCaisseJournalController::class, 'total_encaissement']);
    Route::get('caissier/caisses-journal-total-decaissement', [CaissierCaisseJournalController::class, 'total_decaissement']);
    Route::get('caissier/caisses-journal-total-caisse', [CaissierCaisseJournalController::class, 'total_caisse']);
    Route::get('montant-total-decaissement', [DecaissementController::class, 'montantTotalDecaissement']);
    Route::get('decaissements-attente', [DecaissementController::class, 'getDecaissemenentEnAttente']);

    Route::get('stocks/ruptures', [StockController::class, 'ruptures']);
    Route::get('produits-ruptures', [ProduitController::class, 'produits_en_rupture']);
    Route::get('nombre-produits', [ProduitController::class, 'nombreProduits']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);
    #annulerTransfer
    Route::post('stocks/transfer/annuler', [StockController::class, 'annulerTransfer']);

    Route::post('stocks/reapprovisionner', [StockController::class, 'reapprovisionner']);

    Route::apiResource('commandes', CommandeController::class);
    # les commandes payee aujourduih
    Route::get('commandes-payees-aujourdhui', [CommandeController::class, 'commandesPayeesAujourdhui']);
    Route::get('commandes-attente', [CommandeController::class, 'getCommandesEnAttente']);
    Route::get('commandes-payees', [CommandeController::class, 'getCommandesValidees']);
    Route::get('commandes-annulees', [CommandeController::class, 'getCommandesAnnulees']);
    Route::post('commandes/{commande}/valider', [CommandeController::class, 'valider']);
    Route::post('commandes/{commande}/annuler', [CommandeController::class, 'annuler']);
    #•Montant total des commandes (clients normaux + spéciaux).
    Route::get('montant-total-commandes', [CommandeController::class, 'montantTotalCommandes']);
    #la liste des commsndes effectuer par un vendeur donnee
    Route::get('commandes-par-vendeur/{id}', [CommandeController::class, 'commandesParVendeur']);
    #Total commandes payées
    Route::get('total-commandes-payees', [CommandeController::class, 'totalCommandesPayees']);
    # o	Commandes en attente caisse
    Route::get('commandes-en-attente-caisse', [CommandeController::class, 'commandesEnAttenteCaisse']);



    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::get('paiements-rapport-journalier', [PaiementController::class, 'rapportJournalier']);
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);
    #la somme total des paiements
    Route::get('somme-paiements-total', [PaiementController::class, 'sommeTotalPaiements']);
    #•	Reste total à encaisser.
    Route::get('reste-total-encaisser', [PaiementController::class, 'resteTotalEncaisser']);
    Route::get('nombre-produits-vendus-aujourdhui', [ProduitController::class, 'nombreProduitsVendusAujourdhui']);
    Route::apiResource('utilisateurs', UserController::class);
    Route::post('utilisateurs/{utilisateur}/reset-password', [UserController::class, 'resetPassword']);
    #Nombre total de vendeurs
    Route::get('vendeurs-count', [UserController::class, 'vendeursCount']);
    #Nombre total de caissier
    Route::get('caissiers-count', [UserController::class, 'caissiersCount']);
    #nombre total de gestionnaire boutique
    Route::get('gestionnaires-count', [UserController::class, 'gestionnairesCount']);



    Route::get('mouvements-stock/inventaire-depot', [MouvementSockController::class, 'inventaireDepot']);
    Route::get('nombre-mouvements-stock-today', [MouvementSockController::class, 'nombreMouvementStockToday']);
    Route::get('nombre-mouvements-stock-total', [MouvementSockController::class, 'nombreMouvementStockTotal']);
    Route::post('enregistrer-inventaire-depot', [MouvementSockController::class, 'enregistrerInventaireDepot']);
    #le nombre d'entre dans le  stock
    Route::get('nombre-entree-stock-total', [MouvementSockController::class, 'nombreEntreeStockTotal']);
    #le nombre de sortie dans le  stock
    Route::get('nombre-sortie-stock-total', [MouvementSockController::class, 'nombreSortieStockTotal']);
    Route::post('enregistrer-inventaire-boutique', [HistoriqueVenteController::class, 'enregistrerInventaireBoutique']);
    Route::get('historique-inventaires', [InventaireController::class, 'index']);



    Route::apiResource('mouvements-stock', MouvementSockController::class);
    Route::put('produits/{produit}/reduire-stock', [ProduitController::class, 'reduireStockProduit']);
    #•	Nombre de produits en rupture (nombre_carton==0)
    Route::get('nombre-produits-en-rupture', [ProduitController::class, 'nombreProduitsEnRupture']);
    #nombre de produits sous seuil
    Route::get('nombre-produits-sous-seuil', [ProduitController::class, 'nombreProduitsSousSeuil']);
    #nombre de produits en normaux.
    Route::get('nombre-produits-en-normaux', [ProduitController::class, 'nombreProduitsEnNormaux']);
    Route::get('produits-en-normaux', [ProduitController::class, 'produitsEnNormaux']);
    Route::get('produits-sous-seuil', [ProduitController::class, 'produits_sous_seuil']);




    Route::apiResource('historique-actions', HistoriqueActionController::class);




    Route::post('fond-caisse', [FondCaisseController::class, 'store']);

    # Gestion des enregPtPistrements de versement
    Route::apiResource('enregistrer-versements', EnregistrerVersementController::class);
    Route::get('nombre-versement-total', [EnregistrerVersementController::class, 'nombreTotalVersement']);
    Route::get('nombre-versement-total-by-date', [EnregistrerVersementController::class, 'nombreTotalVersementByDate']);
    Route::get('somme-versement-total', [EnregistrerVersementController::class, 'sommeTotalVersement']);
    Route::get('somme-versement-total-by-date', [EnregistrerVersementController::class, 'sommeTotalVersementByDate']);


    // ---------------- MOUVEMENTS ----------------
    Route::apiResource('mouvements-stock', MouvementSockController::class);
});
