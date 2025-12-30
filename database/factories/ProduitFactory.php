<?php

namespace Database\Factories;

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
        $categories = ['Cahiers', 'Stylos', 'Crayons', 'Règles', 'Gommes', 'Sacs', 'Livres'];
        return [
            'nom' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->bothify('MAT-####')),
            'categorie' => $this->faker->randomElement($categories),
            'unite_carton' => $this->faker->numberBetween(1, 100),
            'nombre_carton' => $this->faker->numberBetween(100, 150),
            'prix_unite_carton' => $this->faker->randomFloat(2, 1000, 50000),
            'prix_vente_detail' => $this->faker->randomFloat(2, 1000, 50000),
            'prix_vente_gros' => $this->faker->randomFloat(2, 800, 40000),
            'prix_achat'=>$this->faker->randomFloat(800,2000),
            'prix_seuil_detail' => $this->faker->randomFloat(2, 500, 30000),
            'prix_seuil_gros' => $this->faker->randomFloat(2, 500, 30000),
            'stock_global' => $this->faker->numberBetween(10, 1000),
            'stock_seuil' => $this->faker->numberBetween(10, 100),
        ];
    }
}
