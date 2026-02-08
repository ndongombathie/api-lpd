<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Models
use App\Models\Fournisseur;
use App\Models\Client;

// Observers
use App\Observers\FournisseurObserver;
use App\Observers\ClientObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Observers
        |--------------------------------------------------------------------------
        | On attache les observers aux modèles.
        | Les observers écoutent automatiquement :
        | created, updated, deleting...
        */

        Fournisseur::observe(FournisseurObserver::class);

        // 👇 IMPORTANT pour les clients spéciaux
        Client::observe(ClientObserver::class);
    }
}
