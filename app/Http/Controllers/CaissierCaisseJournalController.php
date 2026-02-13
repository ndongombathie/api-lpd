<?php

namespace App\Http\Controllers;

use App\Models\CaissierCaisseJournal;
use App\Models\Decaissement;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CaissierCaisseJournalController extends Controller
{
    /**
     * Liste des rapports journaliers (pour interface comptable / export).
     * Query: date_debut, date_fin (Y-m-d), cloture (0|1).
     */
    public function index(Request $request)
    {
        $query = CaissierCaisseJournal::query()->orderByDesc('date');

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }
        if ($request->has('cloture')) {
            $query->where('cloture', (bool) $request->cloture);
        }

        $journals = $query->paginate(10);

        return response()->json($journals);
    }

    public function show(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $journal = CaissierCaisseJournal::firstOrCreate(
            ['date' => $dateStr],
            ['fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr))]
        );

        [$totalEncaissements, $totalDecaissements, $soldeTheorique,$nombrePaiements] = $this->computeTotals($dateStr, (int) $journal->fond_ouverture);

        // Mettre à jour les totaux théoriques (sans écraser solde_reel/observations)
        $journal->fill([
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
            'nombre_paiements'=>$nombrePaiements,
            'solde_theorique' => $soldeTheorique,
        ])->save();

        return response()->json($journal->fresh());
    }

    public function total_encaissement(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $totalEncaissements = (int) Paiement::whereDate('date', $dateStr)->sum('montant');

        return response()->json($totalEncaissements);
    }

    public function total_decaissement(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $totalDecaissements = (int) Decaissement::whereRaw('LOWER(statut) = ?', ['valide'])
            ->whereDate('updated_at', $dateStr)
            ->sum('montant');

        return response()->json($totalDecaissements);
    }

    public function total_caisse(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $totalCaisse = (int) CaissierCaisseJournal::whereDate('date', $dateStr)->sum('solde_theorique');

        return response()->json($totalCaisse);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'fond_ouverture' => 'required|numeric|min:0',
        ]);

        $journal = CaissierCaisseJournal::updateOrCreate(
            ['date' => $data['date']],
            ['fond_ouverture' => (int) $data['fond_ouverture']]
        );

        return response()->json($journal, 201);
    }

    public function cloture(Request $request, string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $data = $request->validate([
            'solde_reel' => 'required|numeric|min:0',
            'observations' => 'nullable|string',
        ]);

        $journal = CaissierCaisseJournal::firstOrCreate(
            ['date' => $dateStr],
            ['fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr))]
        );

        [$totalEncaissements, $totalDecaissements, $soldeTheorique, $nombrePaiements] = $this->computeTotals($dateStr, (int) $journal->fond_ouverture);

        // Mettre à jour les totaux réels et autres informations
        $journal->fill([
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
            'nombre_paiements' => $nombrePaiements,
            'caissier_id' => $request->user()->id,
            'solde_theorique' => $soldeTheorique,
            'solde_reel' => (int) $data['solde_reel'],
            'observations' => $data['observations'] ?? null,
            'cloture' => true,
        ])->save();

        return response()->json($journal->fresh());
    }

    private function computeTotals(string $dateStr, int $fondOuverture): array
    {
        $totalEncaissements = (int) Paiement::whereDate('date', $dateStr)->sum('montant');
        $nombrePaiements = (int) Paiement::whereDate('date', $dateStr)->count();

        $totalDecaissements = (int) Decaissement::whereRaw('LOWER(statut) = ?', ['valide'])
            ->whereDate('updated_at', $dateStr)
            ->sum('montant');

        $soldeTheorique = (int) ($fondOuverture + $totalEncaissements - $totalDecaissements);

        return [$totalEncaissements, $totalDecaissements, $soldeTheorique, $nombrePaiements];
    }

    private function getFondOuverture(Carbon $date): int
    {
        $veille = $date->copy()->subDay()->toDateString();

        $rapportVeille = CaissierCaisseJournal::where('date', $veille)
            ->where('cloture', true)
            ->first();

        if (!$rapportVeille) {
            return 0;
        }

        return (int) ($rapportVeille->solde_reel ?? $rapportVeille->solde_theorique ?? 0);
    }
}

