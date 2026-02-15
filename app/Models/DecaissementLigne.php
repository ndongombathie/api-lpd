<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecaissementLigne extends Model
{
    protected $fillable = ['decaissement_id', 'libelle', 'montant'];

    public function decaissement()
    {
        return $this->belongsTo(
            \App\Models\Decaissement::class,
            'decaissement_id',
            'id'
        );
    }
}
