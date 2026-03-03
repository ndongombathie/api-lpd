<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\TransfertEnAttente;
use Illuminate\Support\Facades\Auth;

class TransfertValidee
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransfertEnAttente $transfert)
    {
        $this->transfert->loadMissing(['produit']);
    }

    public function broadcastOn(): array
    { 
        $transfertId = $this->transfert->id;
        $boutiqueId = Auth::user()->boutique_id;
        return [
            new PrivateChannel('transfert.' . $transfertId),
            new PrivateChannel('boutique.' . $boutiqueId),
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
