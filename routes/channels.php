<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('boutique.{boutiqueId}', function ($user, string $boutiqueId) {
    return (string) $user->boutique_id === (string) $boutiqueId && in_array($user->role, ['gestionnaire_boutique','caissier', 'vendeur','responsable']);
});

Broadcast::channel('commande.{commandeId}', function ($user, string $commandeId) {
    // Autoriser les membres de la boutique du vendeur ou administrateurs.
    // Simplification: accès si connecté; le front filtrera par boutique.
    return !empty($user->id);
});

Broadcast::channel('transfert.{transfertId}', function ($user, $transfertId) {
    return true; // ou logique spécifique
});
