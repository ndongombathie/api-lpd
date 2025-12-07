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
        'photo',         // photo de profil éventuelle
        'is_online',     // présence temps réel
        'last_login_at', // dernière connexion
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
            'password'          => 'hashed',
            'last_login_at'     => 'datetime',
            'is_online'         => 'boolean',
        ];
    }

    // ============================
    // Relations
    // ============================

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function ventes(): HasMany
    {
        // si ton modèle de vente s'appelle Commande et a vendeur_id
        return $this->hasMany(Commande::class, 'vendeur_id');
    }

    // 🔔 Relation vers les notifications (cloche)
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Helper pratique pour pousser une notification pour ce user.
     */
    public function pushNotification(
        string $module,
        string $type,
        string $title,
        ?string $message = null,
        ?string $url = null,
        array $data = []
    ): void {
        $this->notifications()->create([
            'module'  => $module,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'url'     => $url,
            'data'    => $data,
        ]);
    }
}
