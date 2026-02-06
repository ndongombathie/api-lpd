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
        'categorie',
        'prix_vente',
        'prix_gros',
        'prix_seuil',
        'stock_global'
    ];

    // =========================
    // 🔵 SYSTÈME VENDEUR
    // =========================
    public function details(): HasMany
    {
        return $this->hasMany(DetailCommande::class);
    }

    // =========================
    // 🟣 SYSTÈME RESPONSABLE
    // =========================
    public function lignesSpeciales(): HasMany
    {
        return $this->hasMany(CommandeLigne::class);
    }

    // =========================
    // 🔗 COMMUN
    // =========================
    public function stocks(): HasMany
    {
        return $this->hasMany(StockBoutique::class);
    }

    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementStock::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function entreees_sorties(): HasMany
    {
        return $this->hasMany(EntreeSortie::class);
    }
}
