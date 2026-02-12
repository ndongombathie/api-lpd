<?php

namespace Database\Seeders;

use App\Models\EntreeSortieBoutique;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EntreeSortieBoutiqueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       EntreeSortieBoutique::factory()->count(40)->create();
    }
}
