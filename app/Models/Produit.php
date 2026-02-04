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
        'nom', 'code', 'categorie', 'prix_vente', 'prix_gros', 'prix_seuil', 'stock_global'
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
      return $this->belongsTo(categorie::class);
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
