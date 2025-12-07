<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nom',
        'prenom',
        'entreprise',
        'adresse',
        'numero_cni',
        'telephone',
        'type_client',
        'solde',
        'contact',
    ];

    public function commandes(): HasMany
    {
        return $this->hasMany(Commande::class);
    }
}

