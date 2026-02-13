<?php

namespace App\Http\Controllers;

use App\Models\CaissierCaisseJournal;
use App\Models\Commande;
use App\Models\Decaissement;
use App\Models\fondCaisse;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CaissierDashboardController extends Controller
{
    public function stats(Request $request)
    {
        $dateStr = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateStr)->startOfDay();

        $fondOuverture = $this->getFondOuverture($date);

        // Total encaissements du jour = somme des paiements du jour (heure exacte)
        $totalEncaissements = (int) Paiement::whereDate('date', $dateStr)
        ->where('caissier_id', Auth::user()->id)
        ->sum('montant');

        // Total décaissements du jour = décaissements "valide" validés ce jour-là (updated_at)
        $totalDecaissements = (int) Decaissement::whereRaw('LOWER(statut) = ?', ['valide'])
            ->whereDate('updated_at', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->sum('montant');

        $soldeActuel = (int) ($fondOuverture + $totalEncaissements - $totalDecaissements);

        $ticketsEnAttente = (int) Commande::whereRaw('LOWER(statut) = ?', ['attente'])->count();

        // Tickets traités = commandes passées à "payee" ce jour-là (updated_at)
        $ticketsTraites = (int) Commande::whereRaw('LOWER(statut) = ?', ['payee'])
            ->whereDate('updated_at', $dateStr)
            ->count();

        return response()->json([
            'date' => $dateStr,
            'fond_ouverture' => $fondOuverture,
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
            'solde_actuel' => $soldeActuel,
            'tickets_en_attente' => $ticketsEnAttente,
            'tickets_traites' => $ticketsTraites,
        ]);
    }

    public function ventesParMoyen(Request $request)
    {
        $dateStr = $request->query('date', now()->toDateString());

        $rows = Paiement::query()
            ->selectRaw('type_paiement, SUM(montant) as montant')
            ->whereDate('date', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->groupBy('type_paiement')
            ->get();

        $total = (int) $rows->sum('montant');

        $labels = [
            'especes' => 'Espèces',
            'carte' => 'Carte',
            'wave' => 'Wave',
            'Orange Money' => 'Orange Money',
            'autre' => 'Autre',
        ];

        $ventes = $rows->map(function ($row) use ($labels, $total) {
            $type = $row->type_paiement ?? 'especes';
            $montant = (int) ($row->montant ?? 0);
            return [
                'type' => $type,
                'moyen' => $labels[$type] ?? $type,
                'montant' => $montant,
                'pourcentage' => $total > 0 ? (int) round(($montant / $total) * 100) : 0,
            ];
        })->values();

        return response()->json([
            'date' => $dateStr,
            'ventes' => $ventes,
            'total' => $total,
        ]);
    }

    public function ventesParHeure(Request $request)
    {
        $dateStr = $request->query('date', now()->toDateString());

        $tranches = [
            '08h-10h' => 0,
            '10h-12h' => 0,
            '12h-14h' => 0,
            '14h-16h' => 0,
            '16h-18h' => 0,
            '18h-20h' => 0,
        ];

        $paiements = Paiement::whereDate('date', $dateStr)->get(['montant', 'date'])
        ->where('caissier_id', Auth::user()->id)
        ;

        foreach ($paiements as $p) {
            $dt = Carbon::parse($p->date);
            $heure = (int) $dt->hour;

            $tranche = '18h-20h';
            if ($heure >= 8 && $heure < 10) $tranche = '08h-10h';
            elseif ($heure >= 10 && $heure < 12) $tranche = '10h-12h';
            elseif ($heure >= 12 && $heure < 14) $tranche = '12h-14h';
            elseif ($heure >= 14 && $heure < 16) $tranche = '14h-16h';
            elseif ($heure >= 16 && $heure < 18) $tranche = '16h-18h';

            $tranches[$tranche] += (int) ($p->montant ?? 0);
        }

        $result = [];
        foreach ($tranches as $heure => $montant) {
            $result[] = ['heure' => $heure, 'montant' => (int) $montant];
        }

        return response()->json([
            'date' => $dateStr,
            'ventes' => $result,
        ]);
    }

    private function getFondOuverture(Carbon $date): int
    {
        $veille = $date->copy()->subDay()->toDateString();

        $rapportVeille = fondCaisse::where('date', $veille)
            ->where('caissier_id', Auth::user()->id)
            ->first();

        if (!$rapportVeille) {
            return 0;
        }

        return (int) ($rapportVeille->solde_reel ?? $rapportVeille->solde_theorique ?? 0);
    }
}

