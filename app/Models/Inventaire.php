<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventaire extends Model
{
    /** @use HasFactory<\Database\Factories\InventaireFactory> */
    use HasFactory;
    use HasUuids;
    protected $fillable = [
        'type',
        'date_debut',
        'date_fin',
        'date',
        'prix_achat_total',
        'prix_valeur_sortie_total',
        'valeur_estimee_total',
        'benefice_total',
    ];

}
