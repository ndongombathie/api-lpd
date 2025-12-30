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
    ];
    
    protected $appends = [
        'statut',
        'methode_paiement',
        'date',
        'montant',
    ];

    public function caissier()
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function getStatutAttribute($value)
    {
        return ucfirst($value);
    }
    public function setStatutAttribute($value)
    {
        $this->attributes['statut'] = strtolower($value);
    }
    public function getMethodePaiementAttribute($value)
    {
        return ucfirst($value);
    }
    public function setMethodePaiementAttribute($value)
    {
        $this->attributes['methode_paiement'] = strtolower($value);
    }
    public function getDateAttribute($value)
    {
        return date('d/m/Y', strtotime($value));
    }
    public function setDateAttribute($value)
    {
        $this->attributes['date'] = date('Y-m-d', strtotime($value));
    }
    public function getMontantAttribute($value)
    {
        return number_format($value, 2, ',', ' ');
    }
    public function setMontantAttribute($value)
    {
        $this->attributes['montant'] = str_replace(',', '', $value);
    }
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
