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
        $this->commande->loadMissing(['details', 'vendeur']);
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
        return [
            'commande' => [
                'id' => $this->commande->id,
                'total' => $this->commande->total,
                'statut' => $this->commande->statut,
                'type_vente' => $this->commande->type_vente,
                'date' => $this->commande->date,
                'vendeur' => [
                    'id' => $this->commande->vendeur->id,
                    'nom' => $this->commande->vendeur->nom ?? null,
                    'boutique_id' => $this->commande->vendeur->boutique_id ?? null,
                ],
                'details' => $this->commande->details->map(fn($d) => [
                    'produit_id' => Produit::find($d->produit_id),
                    'quantite' => $d->quantite,
                    'prix_unitaire' => $d->prix_unitaire,
                ])->toArray(),
            ],
        ];
    }
}