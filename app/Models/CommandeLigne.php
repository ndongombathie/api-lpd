<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CommandeLigne extends Model
{
    use HasUuids;

    protected $fillable = [
        'commande_id',
        'produit_id',
        'libelle',
        'ref',
        'quantite',
        'quantite_unites',
        'mode_vente',
        'prix_unitaire',
        'total_ht',
        'total_ttc',
    ];

    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
