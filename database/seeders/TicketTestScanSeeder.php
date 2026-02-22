<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\User;
use App\Models\Produit;
use App\Models\Client;
use App\Models\Boutique;
use Carbon\Carbon;

/**
 * Crée une commande de test correspondant au ticket en photo
 * pour tester le scan QR : CMD-11134130, vendeur Letitia Stokes, client fv dfv, 21/02/2026 21:58
 */
class TicketTestScanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Création du ticket de test pour le scan QR...');

        // Vendeur : Letitia Stokes
        $vendeur = User::where('nom', 'Stokes')->where('prenom', 'Letitia')->first();
        if (!$vendeur) {
            $boutiqueId = Boutique::first()?->id;
            $vendeur = User::create([
                'nom' => 'Stokes',
                'prenom' => 'Letitia',
                'email' => 'letitia.stokes@test.com',
                'password' => bcrypt('password'),
                'role' => 'vendeur',
                'boutique_id' => $boutiqueId,
            ]);
            $this->command->info('  → Vendeur créé : Letitia Stokes');
        }

        // Client : fv dfv
        $client = Client::where('prenom', 'fv')->where('nom', 'dfv')->first();
        if (!$client) {
            $client = Client::create([
                'nom' => 'dfv',
                'prenom' => 'fv',
                'adresse' => null,
                'telephone' => null,
                'type_client' => 'normal',
                'solde' => 0,
            ]);
            $this->command->info('  → Client créé : fv dfv');
        }

        // ID fixe pour avoir CMD-11134130 sur le ticket
        $commandeId = '11134130-1113-4130-8000-000000000000';

        if (Commande::find($commandeId)) {
            $this->command->warn('  → La commande de test CMD-11134130 existe déjà.');
            return;
        }

        $produits = Produit::all();
        if ($produits->isEmpty()) {
            $this->command->error('  → Aucun produit en base. Lancez d\'abord les seeders des produits.');
            return;
        }

        $produit = $produits->first();
        $prixUnitaire = $produit->prix_vente ?? 5000;
        $quantite = 2;
        $totalHT = $prixUnitaire * $quantite;
        $tva = $totalHT * 0.18;
        $totalTTC = (int) ($totalHT + $tva);

        // Date du ticket : 21/02/2026 21:58
        $dateTicket = Carbon::create(2026, 2, 21, 21, 58, 0);

        Commande::forceCreate([
            'id' => $commandeId,
            'vendeur_id' => $vendeur->id,
            'client_id' => $client->id,
            'statut' => 'attente',
            'type_vente' => 'detail',
            'date' => $dateTicket,
            'total' => $totalTTC,
        ]);

        DetailCommande::create([
            'commande_id' => $commandeId,
            'produit_id' => $produit->id,
            'quantite' => $quantite,
            'prix_unitaire' => $prixUnitaire,
        ]);

        $this->command->info('✅ Ticket de test créé : CMD-11134130');
        $this->command->info('   Vendeur : Letitia Stokes | Client : fv dfv | Date : 21/02/2026 21:58');
        $this->command->info('   Vous pouvez imprimer un ticket avec ce numéro et tester le scan QR.');
    }
}
