<?php

namespace App\Observers;

use App\Models\Fournisseur;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class FournisseurObserver
{
    public function created(Fournisseur $fournisseur)
    {
        AuditLog::create([
            'action' => 'creation',
            'module' => 'fournisseurs',
            'cible_id' => $fournisseur->id,
            'cible_nom' => $fournisseur->nom,
            'utilisateur' => Auth::user()->name ?? 'System',
            'apres' => $fournisseur->toArray(),
        ]);
    }

    public function updated(Fournisseur $fournisseur)
    {
        AuditLog::create([
            'action' => 'modification',
            'module' => 'fournisseurs',
            'cible_id' => $fournisseur->id,
            'cible_nom' => $fournisseur->nom,
            'utilisateur' => Auth::user()->name ?? 'System',
            'avant' => $fournisseur->getOriginal(),
            'apres' => $fournisseur->getChanges(),
        ]);
    }

    public function deleted(Fournisseur $fournisseur)
    {
        AuditLog::create([
            'action' => 'suppression',
            'module' => 'fournisseurs',
            'cible_id' => $fournisseur->id,
            'cible_nom' => $fournisseur->nom,
            'utilisateur' => Auth::user()->name ?? 'System',
            'avant' => $fournisseur->toArray(),
        ]);
    }
}
