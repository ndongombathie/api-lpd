<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        return [
            'boutique_id'=> Boutique::inRandomOrder()->value('id') ?? Boutique::factory(),
            'produit_id'=> Produit::inRandomOrder()->value('id') ?? Produit::factory(),
            'quantite'=>$this->faker->numberBetween(50,100),
            'nombre_carton'=>$this->faker->numberBetween(20,50),
            'seuil'=>$this->faker->numberBetween(10,15)
        ];
    }
}
