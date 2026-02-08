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
use App\Http\Controllers\TransferController;
use App\Http\Controllers\DecaissementController;
use App\Http\Controllers\MouvementSockController;
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

    // ---------------- CLIENTS ----------------
    Route::get('clients/{client}/paiements-tranches', [ClientController::class, 'paiementsTranches']);
    Route::get('clients/{client}/paiements', [ClientController::class, 'paiementsTranches']);

    // ---------------- COMMANDES ----------------
    Route::apiResource('commandes', CommandeController::class);
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
    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::put('paiements/{paiement}', [PaiementController::class, 'update']);
    Route::delete('paiements/{paiement}', [PaiementController::class, 'destroy']);

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
    Route::get('stocks', [StockController::class, 'index']);
    Route::get('stocks/ruptures', [StockController::class, 'ruptures']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);
    Route::post('stocks/reapprovisionner', [StockController::class, 'reapprovisionner']);

    Route::get('produits-ruptures', [ProduitController::class, 'produits_en_rupture']);

    // ---------------- TRANSFERTS ----------------
    Route::get('transfers/boutique/{boutique_id}', [TransferController::class, 'produitsByBoutique']);
    Route::get('transfers/valide', [TransferController::class, 'getTransferValide']);
    Route::get('produits-transfer', [TransferController::class, 'index']);
    Route::put('valider-produits-transfer', [TransferController::class, 'valideTransfer']);
    Route::get('produits-disponibles-boutique', [TransferController::class, 'produitsDisponibles']);
    Route::get('nombre-produits-total', [TransferController::class, 'nombreProduits']);
    Route::get('quantite-totale-produit', [TransferController::class, 'quantiteTotaleProduit']);
    Route::get('produits-sous-seuil', [TransferController::class, 'produitsSousSeuil']);
    Route::get('montant-total-stock', [TransferController::class, 'MontantTotalStock']);

    // ---------------- DECAISSEMENTS ----------------

    Route::get('decaissements/export', [DecaissementController::class, 'exportAll']);

    Route::apiResource('decaissements', DecaissementController::class);
    Route::put('decaissements/{decaissement}/statut', [DecaissementController::class, 'updateStatusDecaissement']);
    Route::get('montant-total-decaissement', [DecaissementController::class, 'montantTotalDecaissement']);
    Route::get('decaissements-attente', [DecaissementController::class, 'getDecaissemenentEnAttente']);


    // ---------------- MOUVEMENTS ----------------
    Route::apiResource('mouvements-stock', MouvementSockController::class);
});
