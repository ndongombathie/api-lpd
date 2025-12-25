<?php

namespace Database\Factories;

use App\Models\Decaissement;
use Illuminate\Database\Eloquent\Factories\Factory;

class DecaissementFactory extends Factory
{
    protected $model = Decaissement::class;

    public function definition(): array
    {
        $statut = fake()->randomElement(['en attente', 'validé', 'refusé']);

        return [
            'reference'      => 'DEC-' . fake()->unique()->numerify('2025-####'),
            'date_prevue'    => fake()->dateTimeBetween('-1 month', '+1 month'),
            'motif_global'   => fake()->sentence(4),
            'methode_prevue' => fake()->randomElement(['Espèces', 'Virement', 'Mobile Money']),
            'statut'         => $statut,
            'montant_total'  => 0,
            'demandeur_id'   => 1,
            'traite_par_id'  => null,
        ];
    }
}
