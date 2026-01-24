<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class CaissierCaisseJournal extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'caisses_journal';

    protected $fillable = [
        'date',
        'fond_ouverture',
        'total_encaissements',
        'total_decaissements',
        'solde_theorique',
        'solde_reel',
        'cloture',
        'observations',
    ];

    protected $casts = [
        'date' => 'date',
        'fond_ouverture' => 'integer',
        'total_encaissements' => 'integer',
        'total_decaissements' => 'integer',
        'solde_theorique' => 'integer',
        'solde_reel' => 'integer',
        'cloture' => 'boolean',
    ];
}

