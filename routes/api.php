<?php

use Illuminate\Support\Facades\Route;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use App\Http\Controllers\HistoriqueVenteController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\DecaissementController;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    $user = $request->user();

    Log::info('Broadcasting Auth Attempt', [
        'user_id' => $user->id,
        'user_boutique_id' => $user->boutique_id,
        'user_role' => $user->role,
        'socket_id' => $request->input('socket_id'),
        'channel_name' => $request->input('channel_name'),
    ]);

    try {
        $response = Broadcast::auth($request);
        Log::info('Broadcasting Auth Success', ['response' => $response]);
        return $response;
    } catch (\Exception $e) {
        Log::error('Broadcasting Auth Failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        throw $e;
    }
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('montant-total-boutique', [BoutiqueController::class, 'montantTotalBoutique']);
    Route::get('benefice-boutique', [BoutiqueController::class, 'BeneficeBoutique']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('mon-profil', [AuthController::class, 'monProfil']);
    Route::put('mon-profil', [AuthController::class, 'updateProfil']);
    Route::put('change-password', [AuthController::class, 'changePassword']);

    Route::apiResource('categories',CategorieController::class);
    Route::apiResource('produits', ProduitController::class);
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/paiements-tranches', [ClientController::class, 'paiementsTranches']);
    Route::apiResource('fournisseurs', FournisseurController::class);

    Route::get('transfers/boutique/{boutique_id}', [TransferController::class, 'produitsByBoutique']);

    Route::get('transfers/valide', [TransferController::class, 'getTransferValide']);
    Route::get('produits-transfer', [TransferController::class, 'index']);
    Route::put('valider-produits-transfer', [TransferController::class, 'valideTransfer']);
    Route::get('produits-disponibles-boutique', [TransferController::class, 'produitsDisponibles']);
    Route::get('nombre-produits-total', [TransferController::class, 'nombreProduits']);
    Route::get('quantite-totale-produit', [TransferController::class, 'quantiteTotaleProduit']);
    Route::get('produits-sous-seuil', [TransferController::class, 'produitsSousSeuil']);
    Route::get('montant-total-stock', [TransferController::class, 'MontantTotalStock']);



    # Gestion des historiques de vente
    Route::get('index', [HistoriqueVenteController::class, 'index']);
    Route::get('total-vente-par-jour', [HistoriqueVenteController::class, 'totalParJour']);
    Route::get('inventaires-boutique', [HistoriqueVenteController::class, 'inventaireBoutique']);


    Route::get('stocks', [StockController::class, 'index']);
    Route::apiResource('decaissements', DecaissementController::class);
    Route::put('decaissements/{decaissement}/statut', [DecaissementController::class, 'updateStatusDecaissement']);
    Route::get('montant-total-decaissement', [DecaissementController::class, 'montantTotalDecaissement']);
    Route::get('decaissements-attente', [DecaissementController::class, 'getDecaissemenentEnAttente']);

    Route::get('stocks/ruptures', [StockController::class, 'ruptures']);
    Route::get('produits-ruptures', [ProduitController::class, 'produits_en_rupture']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);

    Route::post('stocks/reapprovisionner', [StockController::class, 'reapprovisionner']);

    Route::apiResource('commandes', CommandeController::class);
    Route::get('commandes-attente', [CommandeController::class, 'getCommandesEnAttente']);
    Route::get('commandes-payees', [CommandeController::class, 'getCommandesValidees']);
    Route::get('commandes-annulees', [CommandeController::class, 'getCommandesAnnulees']);
    Route::post('commandes/{commande}/valider', [CommandeController::class, 'valider']);
    Route::post('commandes/{commande}/annuler', [CommandeController::class, 'annuler']);

    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);
    Route::apiResource('utilisateurs', UserController::class);
});

