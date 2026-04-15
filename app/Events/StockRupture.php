<?php

namespace App\Events;

use App\Models\StockBoutique;
use App\Models\Produit;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockRupture implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Produit $produit, public string $boutique_id)
    {
        $this->produit->loadMissing(['details']);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('boutique.' . $this->boutique_id)];
    }

    public function broadcastAs(): string
    {
        return 'stock.rupture';
    }

    public function broadcastWith(): array
    {
        return [
            'produit' => [
                'boutique_id' => $this->boutique_id,
                'quantite' => $this->produit->stock_global,
                'produit' => [
                    'nom' => $this->produit->nom ?? null,
                    'code' => $this->produit->code ?? null,
                ],
            ],
        ];
    }
}
