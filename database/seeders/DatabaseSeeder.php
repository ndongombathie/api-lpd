<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Boutique;
use App\Models\CaissierCaisseJournal;
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
use App\Models\EntreeSortieBoutique;
use App\Models\EntreeSortie;
use App\Models\HistoriqueAction;
use App\Models\HistoriqueVente;
use App\Models\Transfer;
use App\Models\Inventaire;
use App\Models\TransfertEnAttente;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $boutiques = Boutique::factory()->count(1)->create();

        $premiereBoutique = $boutiques->first();

        // Admin par défaut
        User::factory()->create([
            'nom' => 'Comptable',
            'prenom' => 'LPD',
            'email' => 'papisdu91@gmail.com',
            'email_hash' => hash('sha256', 'papisdu91@gmail.com'),
            'role' => 'comptable',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Siège',
            'adresse_hash' => hash('sha256', 'Siège'),
            'telephone' => '+237600000000',
            'telephone_hash' => hash('sha256', '+237600000000'),
            'numero_cni' => 'ADMIN0000',
            'email_hash' => hash('sha256', 'papisdu91@gmail.com'),
            'password' => 'password',
        ]);

       /*  // Caissier de test (interface caissier)
        User::factory()->create([
            'nom' => 'Responsable',
            'prenom' => 'LPD',
            'email' => 'responsable@lpd.com',
            'email_hash' => hash('sha256', 'responsable@lpd.com'),
            'role' => 'responsable',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Caisse',
            'adresse_hash' => hash('sha256', 'Caisse'),
            'telephone' => '+237600000001',
            'telephone_hash' => hash('sha256', '+237600000001'),
            'numero_cni' => 'CAISSE01',
            'email_hash' => hash('sha256', 'responsable@lpd.com'),
            'password' => 'password',
        ]);

        User::factory()->create([
            'nom' => 'Caissier',
            'prenom' => 'LPD',
            'email' => 'caissier@lpd.com',
            'email_hash' => hash('sha256', 'caissier@lpd.com'),
            'role' => 'caissier',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Caisse',
            'adresse_hash' => hash('sha256', 'Caisse'),
            'telephone' => '+237600000001',
            'telephone_hash' => hash('sha256', '+237600000001'),
            'numero_cni' => 'CAISSE01',
            'numero_cni_hash' => hash('sha256', 'CAISSE01'),
            'password' => 'password',
        ]);

        User::factory()->create([
            'nom' => 'Vendeur',
            'prenom' => 'LPD',
            'email' => 'vendeur@lpd.com',
            'email_hash' => hash('sha256', 'vendeur@lpd.com'),
            'role' => 'vendeur',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Vendeur',
            'adresse_hash' => hash('sha256', 'Vendeur'),
            'telephone' => '+237600000001',
            'telephone_hash' => hash('sha256', '+237600000001'),
            'numero_cni' => 'CAISSE01',
            'numero_cni_hash' => hash('sha256', 'CAISSE01'),
            'password' => 'password',
        ]);

        User::factory()->create([
            'nom' => 'gestionnaire boutique',
            'prenom' => 'LPD',
            'email' => 'gestionnaire_boutique@lpd.com',
            'email_hash' => hash('sha256', 'gestionnaire_boutique@lpd.com'),
            'role' => 'gestionnaire_boutique',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Gestionnaire',
            'adresse_hash' => hash('sha256', 'Gestionnaire'),
            'telephone' => '+237600000001',
            'telephone_hash' => hash('sha256', '+237600000001'),
            'numero_cni' => 'CAISSE01',
            'numero_cni_hash' => hash('sha256', 'CAISSE01'),
            'password' => 'password',
        ]);

        User::factory()->create([
            'nom' => 'gestionnaire depot',
            'prenom' => 'LPD',
            'email' => 'gestionnaire_depot@lpd.com',
            'email_hash' => hash('sha256', 'gestionnaire_depot@lpd.com'),
            'role' => 'gestionnaire_depot',
            'boutique_id' => optional($premiereBoutique)->id,
            'adresse' => 'Gestionnaire',
            'adresse_hash' => hash('sha256', 'Gestionnaire'),
            'telephone' => '+237600000001',
            'telephone_hash' => hash('sha256', '+237600000001'),
            'numero_cni' => 'CAISSE01',
            'numero_cni_hash' => hash('sha256', 'CAISSE01'),
            'password' => 'password',
        ]); */
    }

}

