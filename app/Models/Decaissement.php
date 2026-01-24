<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Decaissement extends Model
{
    /** @use HasFactory<\Database\Factories\DecaissementFactory> */
    use HasFactory,HasUuids;
    protected $fillable = [
        'user_id',
        'caissier_id',
        'motif',
        'libelle',
        'montant',
        'methode_paiement',
        'date',
        'statut',
    ];
    protected $casts = [
        'montant' => 'integer',
        'date' => 'date',
    ];
    
    // Ne pas utiliser $appends pour éviter les transformations automatiques
    // Les accesseurs seront utilisés seulement quand nécessaire (pour l'affichage)

    // Désactiver les accesseurs pour l'API - retourner les valeurs brutes
    protected $hidden = [];

    public function caissier()
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function setStatutAttribute($value)
    {
        $this->attributes['statut'] = strtolower($value);
    }
    public function setMethodePaiementAttribute($value)
    {
        $this->attributes['methode_paiement'] = strtolower($value);
    }
    // Ne pas transformer la date automatiquement - garder le format ISO
    // Le formatage sera fait côté frontend si nécessaire
    // Ne pas formater le montant automatiquement - garder la valeur numérique
    // Le formatage sera fait côté frontend si nécessaire
    public function getLibelleAttribute($value)
    {
        return ucfirst($value);
    }
    public function setLibelleAttribute($value)
    {
        $this->attributes['libelle'] = strtolower($value);
    }
    public function getMotifAttribute($value)
    {
        return ucfirst($value);
    }
    public function setMotifAttribute($value)
    {
        $this->attributes['motif'] = strtolower($value);
    }


}
