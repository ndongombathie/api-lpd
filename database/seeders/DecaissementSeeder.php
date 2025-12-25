<?php

namespace Database\Seeders;

use App\Models\Decaissement;
use App\Models\DecaissementLigne;
use Illuminate\Database\Seeder;

class DecaissementSeeder extends Seeder
{
    public function run(): void
    {
        Decaissement::factory()
            ->count(10)
            ->create()
            ->each(function (Decaissement $dec) {
                // 1 à 3 lignes par décaissement
                $lignes = DecaissementLigne::factory()
                    ->count(rand(1, 3))
                    ->make();

                $total = $lignes->sum('montant');

                $dec->lignes()->saveMany($lignes);
                $dec->update(['montant_total' => $total]);
            });
    }
}
