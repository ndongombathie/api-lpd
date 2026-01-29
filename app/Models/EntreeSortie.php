<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntreeSortie extends Model
{
    /** @use HasFactory<\Database\Factories\EntreeSortieFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = ['produit_id','quantite_avant','quantite_apres'];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
