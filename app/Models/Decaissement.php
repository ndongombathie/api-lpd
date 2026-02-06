<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Decaissement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        // caissier (existant)
        'user_id',
        'caissier_id',
        'motif',
        'libelle',
        'montant',
        'methode_paiement',
        'date',
        'statut',

        // responsable (ajoutés)
        'motif_global',
        'methode_prevue',
        'date_prevue',
        'montant_total',
    ];
// App\Models\Decaissement.php
public function lignes()
{
    return $this->hasMany(
        \App\Models\DecaissementLigne::class,
        'decaissement_id', // clé dans decaissement_lignes
        'id'              // clé dans decaissements
    );
}


}
