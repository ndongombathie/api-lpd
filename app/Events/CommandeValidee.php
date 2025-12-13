<?php

namespace App\Events;

use App\Models\Commande;
use App\Models\Produit;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandeValidee implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Commande $commande)
    {
        $this->commande->loadMissing(['details', 'vendeur','client']);
    }

    public function broadcastOn(): array
    {
        $boutiqueId = optional($this->commande->vendeur)->boutique_id;
        return [new PrivateChannel('boutique.' . $boutiqueId)];
    }

    public function broadcastAs(): string
    {
        return 'commande.validee';
    }

    public function broadcastWith(): array
    {
        return $this->commande->toArray();
    }
}