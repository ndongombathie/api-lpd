<?php

namespace App\Events;

use App\Models\StockBoutique;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockRupture implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public StockBoutique $stock)
    {
        $this->stock->loadMissing(['produit']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('boutique.' . $this->stock->boutique_id)];
    }

    public function broadcastAs(): string
    {
        return 'stock.rupture';
    }

    public function broadcastWith(): array
    {
        return [
            'stock' => [
                'boutique_id' => $this->stock->boutique_id,
                'produit_id' => $this->stock->produit_id,
                'quantite' => $this->stock->quantite,
                'produit' => [
                    'nom' => $this->stock->produit->nom ?? null,
                    'code' => $this->stock->produit->code ?? null,
                ],
            ],
        ];
    }
}