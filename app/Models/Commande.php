<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'client_id',
        'vendeur_id',
        'total',
        'statut',
        'type_vente',
        'date'
    ];

    // =========================
    // 🔵 SYSTÈME VENDEUR
    // =========================
    public function details(): HasMany
    {
        return $this->hasMany(DetailCommande::class);
    }

    // =========================
    // 🟣 SYSTÈME RESPONSABLE
    // =========================
    public function lignesSpeciales(): HasMany
    {
        return $this->hasMany(CommandeLigne::class);
    }

    // =========================
    // 🔗 COMMUN
    // =========================
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function facture(): HasOne
    {
        return $this->hasOne(Facture::class);
    }

    // ======================================================
    // 🧠 MOTEUR FINANCIER – CAISSE (RESPONSABLE)
    // ======================================================

    /**
     * Total réellement encaissé (somme des paiements)
     */
    public function montantPaye(): int
    {
        // Si la relation est déjà chargée, on l'utilise (rapide + fiable)
        if ($this->relationLoaded('paiements')) {
            return (int) $this->paiements->sum('montant');
        }

        // Sinon on interroge la base
        return (int) $this->paiements()->sum('montant');
    }

    public function resteAPayer(): int
    {
        return max(0, (int) $this->total - $this->montantPaye());
    }

    /**
     * Recalcule le statut caisse
     * RÈGLE MÉTIER OFFICIELLE
     */
    public function recalcStatut()
    {
        // une commande annulée ne change jamais de statut
        if ($this->statut === 'annulee') {
            return;
        }

        $totalPaye = $this->paiements()->sum('montant');

        if ($totalPaye == 0) {
            $this->statut = 'en_attente_caisse';
        }
        elseif ($totalPaye < $this->total) {
            $this->statut = 'partiellement_payee';
        }
        else {
            // paiement EXACT
            $this->statut = 'soldee';
        }

        $this->save();
    }

}
