<?php

namespace Database\Seeders;

use App\Models\EntreeSortie;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntreeSortieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        EntreeSortie::factory()->count(10)->create();
    }
}
