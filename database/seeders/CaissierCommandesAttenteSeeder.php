<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Produit;
use App\Models\User;

class CaissierCommandesAttenteSeeder extends Seeder
{
    public function run(): void
    {
        $vendeur = User::query()->where('role', 'vendeur')->first() ?? User::query()->first();
        if (!$vendeur) {
            return;
        }

        $produit = Produit::query()->first();
        if (!$produit) {
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            $commande = Commande::create([
                'client_id' => null,
                'vendeur_id' => $vendeur->id,
                'type_vente' => 'detail',
                'statut' => 'attente',
                'total' => 0,
                'date' => now(),
            ]);

            $qte = rand(1, 5);
            $prix = $produit->prix_vente ?? 1000;

            DetailCommande::create([
                'commande_id' => $commande->id,
                'produit_id' => $produit->id,
                'quantite' => $qte,
                'prix_unitaire' => $prix,
            ]);

            $commande->update(['total' => $qte * $prix]);
        }
    }
}

