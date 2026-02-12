<?php

namespace Database\Seeders;

use App\Models\entree_sortie_boutique;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntreeSortieBoutiqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       entree_sortie_boutique::factory()->count(40)->create();
    }
}
