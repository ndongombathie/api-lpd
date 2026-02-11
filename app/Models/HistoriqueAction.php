<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoriqueAction extends Model
{
    /** @use HasFactory<\Database\Factories\HistoriqueActionFactory> */
    use HasFactory;
    use HasUuids;
    protected $fillable = [
        'user_id',
        'produit_id',
        'action',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
