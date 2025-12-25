<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Decaissement extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'date_prevue',
        'motif_global',
        'methode_prevue',
        'statut',
        'montant_total',
        'demandeur_id',
        'traite_par_id',
        // PLUS de motif_refus ici
    ];

    protected $casts = [
        'date_prevue' => 'date',
    ];

    public function lignes()
    {
        return $this->hasMany(DecaissementLigne::class);
    }

    public function demandeur()
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function traitePar()
    {
        return $this->belongsTo(User::class, 'traite_par_id');
    }
}
