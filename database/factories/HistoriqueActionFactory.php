<?php

namespace Database\Factories;

use App\Models\Produit;
use App\Models\User;
use GuzzleHttp\Handler\Proxy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HistoriqueAction>
 */
class HistoriqueActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => $this->faker->randomElement(User::pluck('id')->toArray()),
            'produit_id' => $this->faker->randomElement(Produit::pluck('id')->toArray()),
            'action' => $this->faker->randomElement(['Création de produit', 'Modification de produit', 'Suppression de produit']),
        ];
    }
}
