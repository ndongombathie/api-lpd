<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'adresse' => $this->faker->address(),
            'numero_cni' => strtoupper($this->faker->bothify('CNI########')),
            'telephone' => $this->faker->phoneNumber(),
            'type_client' => $this->faker->randomElement(['normal', 'special']),
            'solde' => $this->faker->randomFloat(2, 0, 100000),
            'contact' => $this->faker->email(),
        ];
    }
}
