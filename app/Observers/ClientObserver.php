<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\AuditLog;

class ClientObserver
{
    /**
     * Vérifie si on doit logger (uniquement clients spéciaux)
     */
    private function isClientSpecial(Client $client): bool
    {
        return $client->type_client === 'special';
    }

    /**
     * Handle the Client "created" event.
     */
    public function created(Client $client): void
    {
        if (!$this->isClientSpecial($client)) {
            return;
        }

        AuditLog::create([
            'module'     => 'clients',
            'action'     => 'creation',
            'cible_id'   => $client->id,
            'cible_nom'  => $client->nom,
            'details'    => 'Création du client spécial',
            'avant'      => null,
            'apres'      => $client->toArray(),
        ]);
    }

    /**
     * Handle the Client "updated" event.
     */
    public function updated(Client $client): void
    {
        if (!$this->isClientSpecial($client)) {
            return;
        }

        AuditLog::create([
            'module'     => 'clients',
            'action'     => 'modification',
            'cible_id'   => $client->id,
            'cible_nom'  => $client->nom,
            'details'    => 'Modification du client spécial',
            'avant'      => $client->getOriginal(),
            'apres'      => $client->getChanges(),
        ]);
    }

    /**
     * Handle the Client "deleted" event.
     */
    public function deleted(Client $client): void
    {
        if (!$this->isClientSpecial($client)) {
            return;
        }

        AuditLog::create([
            'module'     => 'clients',
            'action'     => 'suppression',
            'cible_id'   => $client->id,
            'cible_nom'  => $client->nom,
            'details'    => 'Suppression du client spécial',
            'avant'      => $client->toArray(),
            'apres'      => null,
        ]);
    }
}
