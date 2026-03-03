<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\TransfertEnAttente;

class TransfertValidee implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $transfert;
    public $boutiqueId;

    public function __construct(TransfertEnAttente $transfert, $boutiqueId)
    {
        $this->transfert = $transfert;
        $this->boutiqueId = $boutiqueId;

        $this->transfert->loadMissing(['produit']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transfert.' . $this->transfert->id),
            new PrivateChannel('boutique.' . $this->boutiqueId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'transfert.validee';
    }

    public function broadcastWith(): array
    {
        return [
            'transfert' => [
                'id' => $this->transfert->id,
                'quantite' => $this->transfert->quantite,
                'nombre_carton' => $this->transfert->nombre_carton,
                'produit' => [
                    'id' => $this->transfert->produit->id,
                    'nom' => $this->transfert->produit->nom,
                    'code' => $this->transfert->produit->code
                ],
            ],
        ];
    }
}
