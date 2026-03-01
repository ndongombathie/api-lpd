<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Transfer extends Model
{
    /** @use HasFactory<\Database\Factories\TransferFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'boutique_id',
        'produit_id',
        'quantite',
        'seuil',
        'nombre_carton',
        'status',
    ];
    protected $casts = [
        'boutique_id' => 'string',
        'produit_id' => 'string',
        'quantite' => 'integer',
        'seuil' => 'integer',
        'nombre_carton' => 'integer',
        'status' => 'string',
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function boutique()
    {
        return $this->belongsTo(Boutique::class);
    }

    public function mouvementStock()
    {
        return $this->hasOne(MouvementStock::class);
    }

}
