<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EntreeSortieBoutique extends Model
{
    /** @use HasFactory<\Database\Factories\EntreeSortieBoutiqueFactory> */
    use HasFactory;
    use HasUuids;
    protected $table = 'entree_sortie_boutiques';
    protected $fillable = [
        'produit_id',
        'quantite_avant',
        'quantite_apres',
        'nombre_fois',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
