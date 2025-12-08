<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nom',
        'code_barre',
        'categorie',
        'nombre_cartons',
        'unites_par_carton',
        'prix_basique_detail',
        'prix_seuil_detail',
        'prix_basique_gros',
        'prix_seuil_gros',
        'quantite_seuil',
        'stock_global',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(DetailCommande::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(StockBoutique::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }
}
