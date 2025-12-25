<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DecaissementLigne extends Model
{
    use HasFactory;

    protected $fillable = [
        'decaissement_id',
        'libelle',
        'montant',
    ];

    public function decaissement()
    {
        return $this->belongsTo(Decaissement::class);
    }
}
