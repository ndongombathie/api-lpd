<?php

namespace Database\Factories;

use App\Models\Commande;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['cash', 'mobile', 'carte', 'virement'];
        return [
            'commande_id' => fn () => Commande::inRandomOrder()->value('id') ?? Commande::factory()->create()->id,
            'caissier_id' => User::where('role', 'caissier')->inRandomOrder()->value('id') ?? User::factory()->create()->id,
            'montant' => $this->faker->numberBetween(1000, 150000),
            'type_paiement' => $this->faker->randomElement($types),
            'date' => $this->faker->dateTimeBetween('-2 months', 'now'),
            'reste_du' => $this->faker->numberBetween(0, 50000),
        ];
    }
}




