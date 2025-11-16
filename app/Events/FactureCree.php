<?php

namespace App\Events;

use App\Models\Facture;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FactureCree implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Facture $facture)
    {
        $this->facture->loadMissing(['commande.vendeur']);
    }

    public function broadcastOn(): array
    {
        $commandeId = $this->facture->commande_id;
        $boutiqueId = optional($this->facture->commande->vendeur)->boutique_id;
        return [
            new PrivateChannel('commande.' . $commandeId),
            new PrivateChannel('boutique.' . $boutiqueId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'facture.cree';
    }

    public function broadcastWith(): array
    {
        return [
            'facture' => [
                'id' => $this->facture->id,
                'commande_id' => $this->facture->commande_id,
                'total' => $this->facture->total,
                'mode_paiement' => $this->facture->mode_paiement,
                'date' => $this->facture->date,
            ],
        ];
    }
}