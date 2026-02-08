<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'audit_logs';

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Champs autorisés
     */
    protected $fillable = [
        'module',
        'action',
        'cible_id',
        'cible_nom',
        'user_id',
        'details',
        'avant',
        'apres',
    ];

    /**
     * Cast JSON automatique
     */
    protected $casts = [
        'avant' => 'array',
        'apres' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function utilisateur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES (pour RapportController)
    |--------------------------------------------------------------------------
    */

    public function scopeModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeBetweenDates($query, $dateDebut, $dateFin)
    {
        return $query->whereBetween('created_at', [
            $dateDebut . ' 00:00:00',
            $dateFin . ' 23:59:59'
        ]);
    }

    public function scopeRecherche($query, $term)
    {
        return $query->where('cible_nom', 'like', "%{$term}%");
    }
}
