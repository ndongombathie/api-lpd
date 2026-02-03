<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\Paiement;
use App\Models\Decaissement;
use App\Models\User;
use App\Models\Produit;
use App\Models\Client;
use Illuminate\Support\Str;

class DonneesTestCaissierSeeder extends Seeder
{
    /**
     * Générer beaucoup de données de test pour la caisse et les décaissements
     */
    public function run(): void
    {
        // Récupérer ou créer des utilisateurs
        $responsable = User::where('role', 'responsable')->first();
        if (!$responsable) {
            $responsable = User::create([
                'id' => Str::uuid(),
                'nom' => 'Responsable',
                'prenom' => 'Test',
                'email' => 'responsable@test.com',
                'password' => bcrypt('password'),
                'role' => 'responsable',
            ]);
        }

        $vendeurs = User::where('role', 'vendeur')->get();
        if ($vendeurs->isEmpty()) {
            $vendeurs = User::factory()->count(5)->create(['role' => 'vendeur']);
        }

        // Récupérer des produits
        $produits = Produit::all();
        if ($produits->isEmpty()) {
            $produits = Produit::factory()->count(30)->create();
        }

        // Récupérer des clients
        $clients = Client::all();
        if ($clients->isEmpty()) {
            $clients = Client::factory()->count(15)->create();
        }

        $this->command->info('Création de 50 commandes en attente...');
        
        // Créer 50 commandes en attente
        for ($i = 0; $i < 50; $i++) {
            $vendeur = $vendeurs->random();
            $client = fake()->boolean(60) ? $clients->random() : null;
            
            $commande = Commande::create([
                'vendeur_id' => $vendeur->id,
                'client_id' => $client?->id,
                'statut' => 'attente',
                'type_vente' => fake()->randomElement(['detail', 'gros']),
                'date' => now()->subHours(fake()->numberBetween(0, 72)),
                'total' => 0,
            ]);

            // Ajouter 1 à 6 lignes de détail
            $nbLignes = fake()->numberBetween(1, 6);
            $totalHT = 0;

            for ($j = 0; $j < $nbLignes; $j++) {
                $produit = $produits->random();
                $quantite = fake()->numberBetween(1, 15);
                
                $prixUnitaire = $commande->type_vente === 'gros' && $produit->prix_gros
                    ? $produit->prix_gros
                    : ($produit->prix_vente ?? fake()->numberBetween(1000, 80000));

                if (!$prixUnitaire || $prixUnitaire <= 0) {
                    $prixUnitaire = fake()->numberBetween(1000, 80000);
                }

                $ligneTotal = $prixUnitaire * $quantite;
                $totalHT += $ligneTotal;

                DetailCommande::create([
                    'commande_id' => $commande->id,
                    'produit_id' => $produit->id,
                    'quantite' => $quantite,
                    'prix_unitaire' => $prixUnitaire,
                ]);
            }

            // Calculer le total avec TVA (18%)
            $tva = $totalHT * 0.18;
            $commande->total = (int)($totalHT + $tva);
            $commande->save();
        }

        $this->command->info('Création de 30 décaissements en attente...');
        
        // Créer 30 décaissements en attente
        $motifs = [
            'Achat de matériel de bureau',
            'Frais de transport',
            'Paiement fournisseur',
            'Maintenance équipement',
            'Frais de communication',
            'Achat de stock',
            'Paiement salaires',
            'Frais de location',
            'Achat de carburant',
            'Frais de réparation',
            'Achat de fournitures',
            'Paiement factures',
            'Frais de formation',
            'Achat de matériel informatique',
            'Frais de publicité',
        ];

        $libelles = [
            'Fournitures de bureau',
            'Transport marchandises',
            'Facture fournisseur',
            'Réparation matériel',
            'Téléphone et internet',
            'Stock produits',
            'Salaires personnel',
            'Location local',
            'Carburant véhicules',
            'Réparations urgentes',
            'Fournitures diverses',
            'Factures services',
            'Formation équipe',
            'Matériel informatique',
            'Publicité et marketing',
        ];

        $methodesPaiement = ['caisse', 'banque', 'wave', 'om', 'carte'];

        for ($i = 0; $i < 30; $i++) {
            Decaissement::create([
                'user_id' => $responsable->id,
                'caissier_id' => null,
                'motif' => fake()->randomElement($motifs),
                'libelle' => fake()->randomElement($libelles),
                'montant' => fake()->numberBetween(20000, 500000),
                'methode_paiement' => fake()->randomElement($methodesPaiement),
                'date' => now()->subDays(fake()->numberBetween(0, 7))->format('Y-m-d'),
                'statut' => 'en_attente',
            ]);
        }

        $this->command->info('✅ 50 commandes en attente créées !');
        $this->command->info('✅ 30 décaissements en attente créés !');
    }
}

