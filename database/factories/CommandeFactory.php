<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Commande>
 */
class CommandeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuts = ['attente', 'validee', 'payee', 'annulee'];
        $types = ['detail', 'gros'];

        return [
            'client_id' => fn () => Client::inRandomOrder()->value('id') ?? Client::factory(),
            'vendeur_id' => fn () => User::inRandomOrder()->value('id') ?? User::factory(),
            'total' => $this->faker->numberBetween(1000, 300000),
            'statut' => $this->faker->randomElement($statuts),
            'type_vente' => $this->faker->randomElement($types),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }
}




