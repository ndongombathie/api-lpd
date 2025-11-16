<?php

namespace App\Events;

use App\Models\Paiement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaiementCree implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Paiement $paiement)
    {
        $this->paiement->loadMissing(['commande.vendeur']);
    }

    public function broadcastOn(): array
    {
        $commandeId = $this->paiement->commande_id;
        $boutiqueId = optional($this->paiement->commande->vendeur)->boutique_id;
        return [
            new PrivateChannel('commande.' . $commandeId),
            new PrivateChannel('boutique.' . $boutiqueId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'paiement.cree';
    }

    public function broadcastWith(): array
    {
        return [
            'paiement' => [
                'id' => $this->paiement->id,
                'commande_id' => $this->paiement->commande_id,
                'montant' => $this->paiement->montant,
                'type_paiement' => $this->paiement->type_paiement,
                'date' => $this->paiement->date,
                'reste_du' => $this->paiement->reste_du,
            ],
        ];
    }
}