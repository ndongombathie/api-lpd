<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DetailCommande>
 */
class DetailCommandeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $produitId = Produit::inRandomOrder()->value('id') ?? Produit::factory();
        $prix = (int) $this->faker->numberBetween(500, 100000);
        return [
            'produit_id' => $produitId,
            'quantite' => $this->faker->numberBetween(1, 10),
            'prix_unitaire' => $prix,
        ];
    }
}




