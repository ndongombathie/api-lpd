<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EntreeSortieBoutique>
 */
class EntreeSortieBoutiqueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produit_id' => Produit::inRandomOrder()->first()->id,
            'quantite_avant' => $this->faker->numberBetween(0, 1000),
            'quantite_apres' => $this->faker->numberBetween(0, 1000),
            'nombre_fois' => $this->faker->numberBetween(0, 100),
        ];
    }
}
