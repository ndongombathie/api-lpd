<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'commande_id','montant','type_paiement','date','reste_du'
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
    protected static function booted()
    {
        static::creating(function ($paiement) {

            $commande = Commande::find($paiement->commande_id);

            if (!$commande) {
                throw new \Exception('Commande introuvable.');
            }

            // 🔒 commande annulée
            if ($commande->statut === 'annulee') {
                throw new \Exception(
                    'Impossible d\'ajouter un paiement sur une commande annulée.'
                );
            }

            // 🔒 commande soldée
            if ($commande->statut === 'soldee') {
                throw new \Exception(
                    'Cette commande est déjà soldée.'
                );
            }
        });
    }

}
