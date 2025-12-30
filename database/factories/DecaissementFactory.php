<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Decaissement>
 */
class DecaissementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'caissier_id' => \App\Models\User::factory(),
            'motif' => $this->faker->sentence(),
            'libelle' => $this->faker->sentence(),
            'montant' => $this->faker->numberBetween(100, 10000),
            'methode_paiement' => $this->faker->randomElement(['espece', 'carte']),
            'date' => $this->faker->date(),
            'statut' => $this->faker->randomElement(['en_attente', 'valide']),
        ];
    }
}
