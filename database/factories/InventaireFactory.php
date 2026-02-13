<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventaire>
 */
class InventaireFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type'=>$this->faker->randomElement(['Depot','Boutique']),
            'date'=>$this->faker->date(),
            'date_debut'=>$this->faker->date(),
            'date_fin'=>$this->faker->date(),
            'prix_achat_total'=>$this->faker->numberBetween(1000,100000),
            'prix_valeur_sortie_total'=>$this->faker->numberBetween(1000,100000),
            'valeur_estimee_total'=>$this->faker->numberBetween(1000,100000),
            'benefice_total'=>$this->faker->numberBetween(1000,100000),
        ];
    }
}
