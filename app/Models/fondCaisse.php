<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class fondCaisse extends Model
{
    /** @use HasFactory<\Database\Factories\FondCaisseFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'caissier_id',
        'date',
        'montant',
    ];

    protected $table = 'fond_caisses';

    protected $casts = [
        'date' => 'date',
        'montant' => 'integer',
    ];

    public function caissier()
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }


}
