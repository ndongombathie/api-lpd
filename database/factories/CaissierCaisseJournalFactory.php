<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaissierCaisseJournal>
 */
class CaissierCaisseJournalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'fond_ouverture' => $this->faker->numberBetween(0, 1000000),
            'total_encaissements' => $this->faker->numberBetween(0, 1000000),
            'nombre_paiements' => $this->faker->numberBetween(0, 1000000),
            'caissier_id' => User::factory()->create()->id,
            'total_decaissements' => $this->faker->numberBetween(0, 1000000),
            'solde_theorique' => $this->faker->numberBetween(0, 1000000),
            'solde_reel' => $this->faker->numberBetween(0, 1000000),
            'cloture' => $this->faker->boolean(),
            'observations' => $this->faker->sentence(),
        ];
    }
}
