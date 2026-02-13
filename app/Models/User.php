<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasUuids, HasApiTokens;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'adresse',
        'numero_cni',
        'telephone',
        'role',
        'boutique_id',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
        ];

    }


    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Commande::class, 'vendeur_id');
    }

    protected $appends = ['is_online'];

    public function getIsOnlineAttribute(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }
        return now()->diffInMinutes($this->last_seen_at) < 5;
    }

    public function setIsOnlineAttribute(bool $value): void
    {
        $this->is_online = $value ;
    }

    public function historique_actions(): HasMany
    {
        return $this->hasMany(HistoriqueAction::class);
    }

    public function caissier_caisse_journals(): HasMany
    {
        return $this->hasMany(CaissierCaisseJournal::class);
    }

    public function fond_caisses(): HasMany
    {
        return $this->hasMany(FondCaisse::class);
    }
}
