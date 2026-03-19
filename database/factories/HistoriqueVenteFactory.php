<?php

namespace Database\Factories;

use App\Models\Produit;
use App\Models\User;
use App\Models\TransfertEnAttente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HistoriqueVente>
 */
class HistoriqueVenteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendeur_id' => User::where('role', 'vendeur')->inRandomOrder()->first()->id,
            'transfert_en_attente_id' => TransfertEnAttente::inRandomOrder()->first()->id,
            'produit_id' => Produit::inRandomOrder()->first()->id,
            'quantite' => $this->faker->numberBetween(1, 100),
            'prix_unitaire' => $this->faker->numberBetween(10, 1000),
            'montant' => $this->faker->numberBetween(100, 10000),
            'date'=>$this->faker->dateTimeBetween('-1 year', 'now'),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
