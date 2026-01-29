<?php

namespace Database\Seeders;

use App\Models\Boutique;
use App\Models\Produit;
use App\Models\StockBoutique;
use Illuminate\Database\Seeder;

class StockBoutiqueSeeder extends Seeder
{
    public function run(): void
    {
        $boutiques = Boutique::all(['id']);
        $produits = Produit::all(['id']);

        if ($boutiques->isEmpty() || $produits->isEmpty()) {
            return;
        }

        foreach ($boutiques as $boutique) {
            $selection = Produit::inRandomOrder()->limit(min(25, $produits->count()))->pluck('id');
            foreach ($selection as $produitId) {
                StockBoutique::firstOrCreate(
                    [
                        'boutique_id' => $boutique->id,
                        'produit_id' => $produitId,
                    ],
                    [
                        'quantite' => fake()->numberBetween(0, 120),
                        'nombre_carton' => fake()->numberBetween(0, 120),
                    ]
                );
            }
        }
    }
}
