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
use App\Http\Controllers\Api\NotificationController; // 🔔 Cloche notifications
use App\Http\Controllers\Api\DecaissementController; // 💸 Décaissements
use App\Http\Controllers\Api\InventaireController;   // 📊 Inventaire

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

    /*
    |--------------------------------------------------------------------------
    | PROFIL & AUTH
    |--------------------------------------------------------------------------
    */

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

    // 📦 Catalogue light pour le front (Commandes, etc.)
    Route::get('produits/catalogue', [ProduitController::class, 'catalogue']);

    // CRUD complet
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
    |
    |  - Création / liste des paiements d'une commande
    |  - Mise à jour / suppression d'un paiement (tranche, etc.)
    |
    */

    // Création + liste des paiements pour une commande donnée
    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);

    // ✅ Mise à jour / suppression d'un paiement
    Route::put('paiements/{paiement}', [PaiementController::class, 'update']);
    Route::delete('paiements/{paiement}', [PaiementController::class, 'destroy']);
    Route::post('paiements/{paiement}/encaisser', [PaiementController::class, 'encaisser']);

    /*
    |--------------------------------------------------------------------------
    | DÉCAISSEMENTS (Responsable)
    |--------------------------------------------------------------------------
    |
    |  GET   /api/decaissements                 → liste + lignes
    |  POST  /api/decaissements                 → création d’une demande
    |  PATCH /api/decaissements/{id}/statut     → changer le statut
    |
    */
    Route::get('decaissements', [DecaissementController::class, 'index']);
    Route::post('decaissements', [DecaissementController::class, 'store']);
    Route::patch('decaissements/{decaissement}/statut', [DecaissementController::class, 'updateStatut']);

    /*
    |--------------------------------------------------------------------------
    | UTILISATEURS
    |--------------------------------------------------------------------------
    */
    Route::apiResource('users', UserController::class);

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATIONS (cloche du responsable)
    |--------------------------------------------------------------------------
    |
    | GET  /api/notifications                → liste + per_page + unread_total
    | POST /api/notifications/mark-all-read → tout marquer comme lu
    | POST /api/notifications/mark-module   → marquer tout lu pour un module
    | POST /api/notifications/{id}/read     → une notification précise
    |
    */
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/mark-module', [NotificationController::class, 'markByModule']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markOneRead']);

    /*
    |--------------------------------------------------------------------------
    | INVENTAIRE
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventaire')->group(function () {
        Route::get('kpi', [InventaireController::class, 'kpi']);
        Route::get('ajustements', [InventaireController::class, 'index']);
        Route::post('ajustements', [InventaireController::class, 'store']);
        Route::get('ecarts-par-categorie', [InventaireController::class, 'ecartsParCategorie']);
        Route::get('repartition-ecarts', [InventaireController::class, 'repartitionEcarts']);
        Route::get('evolution-valeur-ecarts', [InventaireController::class, 'evolutionValeurEcarts']);
    });
});
