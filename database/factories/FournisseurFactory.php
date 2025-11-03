<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Fournisseur>
 */
class FournisseurFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->company(),
            'contact' => $this->faker->phoneNumber(),
            'adresse' => $this->faker->address(),
            'total_achats' => $this->faker->randomFloat(2, 0, 1000000),
        ];
    }
}
