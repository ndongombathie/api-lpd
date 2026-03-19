<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertEnAttente extends Model
{
    /** @use HasFactory<\Database\Factories\TransfertEnAttenteFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'produit_id',
        'quantite',
        'nombre_carton',
        'seuil',
        'quantite_initial',
        'status',
        'prix_unite_carton',
        'prix_vente_detail',
        'prix_vente_gros',
        'prix_seuil_detail',
        'prix_seuil_gros',
    ];



    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

     public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function mouvementStock()
    {
        return $this->hasOne(MouvementStock::class);
    }
}
