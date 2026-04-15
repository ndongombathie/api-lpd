<?php

namespace App\Events;

use App\Models\TransfertEnAttente;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockBoutiqueMisAJour implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransfertEnAttente $stock, public string $boutique_id)
    {
        $this->stock->loadMissing(['produit']);
    }

    public function broadcastOn(): array
    {
        \Log::info('Broadcast sur canal: boutique.' . $this->boutique_id);
        return [new PrivateChannel('boutique.' . $this->boutique_id)];
    }

    public function broadcastAs(): string
    {
        return 'stock.mis_a_jour';
    }

    public function broadcastWith(): array
    {
        return [
            'stock' => [
                'boutique_id' => $this->boutique_id,
                'produit_id' => $this->stock->produit_id,
                'quantite' => $this->stock->quantite,
                'produit' => [
                    'nom' => $this->stock->produit->nom ?? null,
                    'code' => $this->stock->produit->code ?? null,
                ]
            ],
        ];
    }
}
