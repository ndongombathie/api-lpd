<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailCommande extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'detail_commandes';

    protected $fillable = [
        'commande_id',
        'produit_id',
        'libelle',
        'mode_vente',
        'quantite',         // quantité "affichée" (unités OU cartons)
        'quantite_unites',  // quantité réelle en unités (impact stock_global)
        'prix_unitaire',
        'total_ht',
        'total_ttc',
    ];

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
