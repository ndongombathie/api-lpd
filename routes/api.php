<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
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
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('mon-profil', [AuthController::class, 'monProfil']);
    Route::put('mon-profil', [AuthController::class, 'updateProfil']);




        
    Route::apiResource('produits', ProduitController::class);
    Route::apiResource('clients', ClientController::class);
    Route::get('clients/{client}/paiements-tranches', [ClientController::class, 'paiementsTranches']);
    Route::apiResource('fournisseurs', FournisseurController::class);

    Route::get('stocks', [StockController::class, 'index']);
    Route::get('stocks/ruptures', [StockController::class, 'ruptures']);
    Route::post('stocks/transfer', [StockController::class, 'transfer']);
    Route::post('stocks/reapprovisionner', [StockController::class, 'reapprovisionner']);
    
    Route::apiResource('commandes', CommandeController::class);
    Route::get('commandes-attente', [CommandeController::class, 'getCommandesEnAttente']);
    Route::get('commandes/validees', [CommandeController::class, 'getCommandesValidees']);
    Route::post('commandes/{commande}/valider', [CommandeController::class, 'valider']);
    Route::post('commandes/{commande}/annuler', [CommandeController::class, 'annuler']);

    Route::post('commandes/{commande}/paiements', [PaiementController::class, 'store']);
    Route::get('commandes/{commande}/paiements', [PaiementController::class, 'index']);
    Route::apiResource('uilisateurs', UserController::class);
});


