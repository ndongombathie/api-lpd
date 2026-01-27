<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Produit>
 */
class ProduitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = Categorie::all();
        return [
            'nom' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->bothify('MAT-####')),
            'categorie_id' => Categorie::inRandomOrder()->value('id') ?? Categorie::factory(),
            'unite_carton' => $this->faker->numberBetween(1, 100),
            'nombre_carton' => $this->faker->numberBetween(100, 150),
            'prix_unite_carton' => $this->faker->randomFloat(2, 1000, 50000),
            'prix_achat'=>$this->faker->randomFloat(800,2000),
            'stock_global' => $this->faker->numberBetween(10, 1000),
            'stock_seuil' => $this->faker->numberBetween(10, 100),
        ];
    }
}
