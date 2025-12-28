<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use App\Models\Boutique;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $boutiques = Boutique::all();
        $roles = ['vendeur','caissier','gestionnaire_boutique','gestionnaire_depot','responsable','comptable'];
        return [
            'boutique_id' => fake()->randomElement($boutiques)->id,
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'adresse' => fake()->address(),
            'numero_cni' => strtoupper(fake()->bothify('CNI########')),
            'telephone' => fake()->phoneNumber(),
            'role' => $roles[array_rand($roles)],
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
