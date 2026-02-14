<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnregistrerVersement extends Model
{
    /** @use HasFactory<\Database\Factories\EnregistrerVersementFactory> */
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'caissier_id',
        'montant',
        'observation',
    ];

    public function caissier()
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }
}
