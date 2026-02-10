<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nom',
        'code',
        'categorie_id',
        'fournisseur_id',
        'unite_carton',
        'prix_unite_carton',
        'prix_vente_detail',
        'prix_vente_gros',
        'prix_total',
        'prix_achat',
        'prix_seuil_detail',
        'prix_seuil_gros',
        'nombre_carton',
        'stock_global',
        'stock_seuil',
        'created_at',
        'updated_at',
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

    public function categorie():BelongsTo{
      return $this->belongsTo(Categorie::class);
    }

    public function fournisseur():BelongsTo{
      return $this->belongsTo(Fournisseur::class);
    }

    public function entreees_sorties(): HasMany
    {
        return $this->hasMany(EntreeSortie::class);
    }

    public function entreees_sorties_boutique(): HasMany
    {
        return $this->hasMany(entree_sortie_boutique::class);
    }

    public function historique_actions(): HasMany
    {
        return $this->hasMany(HistoriqueAction::class);
    }
}
