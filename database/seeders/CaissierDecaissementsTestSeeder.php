<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Decaissement;
use App\Models\User;

class CaissierDecaissementsTestSeeder extends Seeder
{
    public function run(): void
    {
        $responsable = User::query()->where('role', 'responsable')->first() ?? User::query()->first();
        $caissier = User::query()->where('role', 'caissier')->first();

        if (!$responsable) {
            return;
        }

        for ($i = 0; $i < 10; $i++) {
            Decaissement::create([
                'user_id' => $responsable->id,
                'caissier_id' => $caissier?->id,
                'motif' => 'Test décaissement caissier',
                'libelle' => 'Test',
                'montant' => rand(1000, 50000),
                'methode_paiement' => 'especes',
                'date' => now()->toDateString(),
                'statut' => 'en_attente',
            ]);
        }
    }
}

