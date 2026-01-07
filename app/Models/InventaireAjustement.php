<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventaireAjustement extends Model
{
    use HasFactory;

    protected $table = 'inventaire_ajustements';

    protected $fillable = [
        'date_comptage',
        'produit',
        'categorie',
        'fournisseur',
        'stock_theorique',
        'stock_reel',
        'ecart',
        'valeur_ecart',
        'motif',
        'user_id',
    ];

    protected $casts = [
        'date_comptage' => 'date',
        'stock_theorique' => 'integer',
        'stock_reel' => 'integer',
        'ecart' => 'integer',
        'valeur_ecart' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
