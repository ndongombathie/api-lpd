<?php

namespace App\Events;

use App\Models\Commande;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandeAnnulee implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Commande $commande)
    {
        $this->commande->loadMissing(['vendeur']);
    }

    public function broadcastOn(): array
    {
        $boutiqueId = optional($this->commande->vendeur)->boutique_id;
        return [new PrivateChannel('boutique.' . $boutiqueId)];
    }

    public function broadcastAs(): string
    {
        return 'commande.annulee';
    }

    public function broadcastWith(): array
    {
        return [
            'commande' => [
                'id' => $this->commande->id,
                'statut' => $this->commande->statut,
            ],
        ];
    }
}