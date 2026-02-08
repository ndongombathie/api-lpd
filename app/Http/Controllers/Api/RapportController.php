<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class RapportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 📊 JOURNAL FOURNISSEURS
    |--------------------------------------------------------------------------
    */
    public function logsFournisseurs(Request $request)
    {
        $query = AuditLog::with('utilisateur')
            ->where('module', 'fournisseurs');

        // filtre période
        if ($request->filled('dateDebut') && $request->filled('dateFin')) {
            $query->whereBetween('created_at', [
                $request->dateDebut . ' 00:00:00',
                $request->dateFin . ' 23:59:59'
            ]);
        }

        // filtre action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // recherche
        if ($request->filled('recherche')) {
            $query->where('cible_nom', 'like', '%' . $request->recherche . '%');
        }

        $perPage = $request->perPage ?? 10;

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'total' => $logs->total(),
            'last_page' => $logs->lastPage(),
            'stats' => $this->calculateStats(
                AuditLog::where('module', 'fournisseurs')
            )
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ⭐ JOURNAL CLIENTS SPECIAUX
    |--------------------------------------------------------------------------
    */
    public function logsClients(Request $request)
    {
        $query = AuditLog::with('utilisateur')
            ->where('module', 'clients');

        // filtre période
        if ($request->filled('dateDebut') && $request->filled('dateFin')) {
            $query->whereBetween('created_at', [
                $request->dateDebut . ' 00:00:00',
                $request->dateFin . ' 23:59:59'
            ]);
        }

        // filtre action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // recherche
        if ($request->filled('recherche')) {
            $query->where('cible_nom', 'like', '%' . $request->recherche . '%');
        }

        $perPage = $request->perPage ?? 10;

        $logs = $query
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'total' => $logs->total(),
            'last_page' => $logs->lastPage(),
            'stats' => $this->calculateStats(
                AuditLog::where('module', 'clients')
            )
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 📊 STATISTIQUES
    |--------------------------------------------------------------------------
    */
    private function calculateStats($query)
    {
        return [
            'total' => (clone $query)->count(),
            'creations' => (clone $query)->where('action', 'creation')->count(),
            'modifications' => (clone $query)->where('action', 'modification')->count(),
            'suppressions' => (clone $query)->where('action', 'suppression')->count(),
        ];
    }
}
