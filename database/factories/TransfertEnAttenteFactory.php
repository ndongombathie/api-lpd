<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\transfertEnAttente>
 */
class TransfertEnAttenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'produit_id'=> Produit::inRandomOrder()->value('id') ?? Produit::factory(),
            'quantite'=>$this->faker->numberBetween(0,100),
            'status'=>$this->faker->randomElement(['en_attente','valide']),
            'nombre_carton'=>$this->faker->numberBetween(20,50),
            'seuil'=>$this->faker->numberBetween(20,30)
        ];
    }
}
