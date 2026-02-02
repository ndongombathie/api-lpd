<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Client;
use App\Models\Fournisseur;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Paiement;
use App\Models\MouvementStock;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Decaissement;
use App\Models\HistoriqueAction;
use App\Models\HistoriqueVente;
use App\Models\Transfer;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $boutiques = Boutique::factory()->count(2)->create();

        // Admin par défaut
        User::factory()->create([
            'nom' => 'Admin',
            'prenom' => 'Global',
            'email' => 'ndongo@example.com',
            'role' => 'admin',
            'boutique_id' => optional($boutiques->first())->id,
            'adresse' => 'Siège',
            'telephone' => '+237600000000',
            'numero_cni' => 'ADMIN0000',
            'password' => 'password',
        ]);

        // Données de base
        Categorie::factory()->count(50)->create();
        Produit::factory()->count(50)->create();
        Client::factory()->count(20)->create();
        Fournisseur::factory()->count(5)->create();
        Transfer::factory()->count(10)->create();


        // Stock initial par boutique
        $this->call(StockBoutiqueSeeder::class);

        // Quelques vendeurs rattachés aux boutiques
        $vendeurs = User::factory()->count(5)->create()->each(function (User $u) use ($boutiques) {
            $u->boutique_id = $boutiques->random()->id;
            $u->save();
        });

        // Commandes avec détails et paiements
        $commandes = Commande::factory()->count(30)->create([
            'vendeur_id' => fn () => ($vendeurs->isNotEmpty() ? $vendeurs->random()->id : User::inRandomOrder()->value('id')),
        ]);

        $commandes->each(function (Commande $commande) {
            $nbLignes = fake()->numberBetween(1, 5);
            $total = 0;
            for ($i = 0; $i < $nbLignes; $i++) {
                $detail = DetailCommande::factory()->make();
                $detail->commande_id = $commande->id;
                $detail->save();
                $total += $detail->quantite * $detail->prix_unitaire;
            }

            // Mettre à jour le total
            $commande->total = $total;

            // Paiements (0 à 2 paiements)
            $nbPaiements = fake()->numberBetween(0, 2);
            $reste = $total;
            for ($j = 0; $j < $nbPaiements; $j++) {
                $montant = $j + 1 === $nbPaiements
                    ? fake()->numberBetween(0, $reste)
                    : fake()->numberBetween(0, (int) floor($reste * 0.7));
                Paiement::factory()->create([
                    'commande_id' => $commande->id,
                    'montant' => $montant,
                    'reste_du' => max(0, $reste - $montant),
                ]);
                $reste -= $montant;
            }

            // Statut basé sur paiements
            if ($reste <= 0 && $total > 0) {
                $commande->statut = 'payee';
            } elseif ($total > 0) {
                $commande->statut = 'validee';
            }

            $commande->save();
        });

        // Mouvements de stock
       // MouvementStock::factory()->count(80)->create();

        // Décaisements
        Decaissement::factory()->count(10)->create();
        HistoriqueVente::factory()->count(50)->create();
        HistoriqueAction::factory()->count(50)->create();
    }

}
