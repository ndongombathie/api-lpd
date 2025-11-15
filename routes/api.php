<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// === Controllers ===
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| AUTHENTIFICATION (Public)
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| ROUTES PROTÉGÉES — SANCTUM + ROLE RESPONSABLE
|--------------------------------------------------------------------------
|
|  🔐 Accès strictement réservé au Responsable.
|
*/
Route::middleware(['auth:sanctum', 'role:responsable'])->group(function () {

    // 🔥 Profil connecté
    Route::get('/mon-profil', function (Request $request) {
        return $request->user();
    });

    // 🔥 Modifier le profil (nom, prénom, photo)
    Route::put('/mon-profil', [ProfileController::class, 'update']);

    // 🔥 Changer mot de passe
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);

    // 🔥 Déconnexion
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | PRODUITS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('produits', ProduitController::class);

    /*
    |--------------------------------------------------------------------------
    | CLIENTS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('clients', ClientController::class);

    /*
    |--------------------------------------------------------------------------
    | FOURNISSEURS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('fournisseurs', FournisseurController::class);

    /*
    |--------------------------------------------------------------------------
    | STOCK / TRANSFERTS
    |--------------------------------------------------------------------------
    */
    Route::get('stocks', [StockController::class, 'index']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);

    /*
    |--------------------------------------------------------------------------
    | COMMANDES
    |--------------------------------------------------------------------------
    */
    Route::apiResource('commandes', CommandeController::class);
    Route::post('commandes/{commande}/valider', [CommandeController::class, 'valider']);
    Route::post('commandes/{commande}/annuler', [CommandeController::class, 'annuler']);

    /*
    |--------------------------------------------------------------------------
    | PAIEMENTS
    |--------------------------------------------------------------------------
    */
    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | UTILISATEURS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('users', UserController::class);

});
