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
    ];
    protected $casts = [
        'quantite' => 'integer',
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
