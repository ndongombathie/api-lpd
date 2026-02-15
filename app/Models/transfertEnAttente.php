<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class transfertEnAttente extends Model
{
    /** @use HasFactory<\Database\Factories\TransfertEnAttenteFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'produit_id',
        'quantite',
        'nombre_carton',
        'seuil',
        'status',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
