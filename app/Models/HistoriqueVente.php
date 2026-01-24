<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriqueVente extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'vendeur_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
        'montant',
        'date',
    ];

    protected $casts = [
        'date' => 'datetime',
        'quantite' => 'integer',
        'prix_unitaire' => 'decimal:2',
        'montant' => 'decimal:2',
    ];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
