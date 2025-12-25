<?php

namespace Database\Factories;

use App\Models\DecaissementLigne;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecaissementLigneFactory extends Factory
{
    protected $model = DecaissementLigne::class;

    public function definition(): array
    {
        return [
            'libelle' => fake()->sentence(3),
            'montant' => fake()->numberBetween(5000, 50000),
        ];
    }
}
