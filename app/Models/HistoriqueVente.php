<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HistoriqueVente extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    use HasUuids;
    protected $fillable = [
        'vendeur_id',
        'produit_id',
        'quantite',
        'prix_unitaire',
        'montant',
    ];
}
