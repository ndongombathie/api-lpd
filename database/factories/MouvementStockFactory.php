<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MouvementStock>
 */
class MouvementStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['entree', 'sortie']);
        $source = null;
        $destination = null;
        if ($type === 'entree') {
            $source = 'fournisseur';
            $destination = 'depot';
        } elseif ($type === 'sortie') {
            $source = 'depot';
            $destination = 'client';
        } else {
            $source = 'depot';
            $destination = 'boutique:'. $this->faker->numberBetween(1, 3);
        }

        return [
            'source' => $source,
            'destination' => $destination,
            'produit_id' => fn () => Produit::inRandomOrder()->value('id') ?? Produit::factory(),
            'quantite' => $this->faker->numberBetween(1, 50),
            'type' => $type,
            'date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }
}




