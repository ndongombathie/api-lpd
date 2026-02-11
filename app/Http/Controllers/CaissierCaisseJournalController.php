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

        $journals = $query->limit(100)->get();

        return response()->json($journals);
    }

    public function show(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $journal = CaissierCaisseJournal::firstOrCreate(
            ['date' => $dateStr],
            ['fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr))]
        );

        [$totalEncaissements, $totalDecaissements, $soldeTheorique] = $this->computeTotals($dateStr, (int) $journal->fond_ouverture);

        // Mettre à jour les totaux théoriques (sans écraser solde_reel/observations)
        $journal->fill([
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
            'solde_theorique' => $soldeTheorique,
        ])->save();

        return response()->json($journal->fresh());
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

        [$totalEncaissements, $totalDecaissements, $soldeTheorique] = $this->computeTotals($dateStr, (int) $journal->fond_ouverture);

        $journal->fill([
            'total_encaissements' => $totalEncaissements,
            'total_decaissements' => $totalDecaissements,
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

        $totalDecaissements = (int) Decaissement::whereRaw('LOWER(statut) = ?', ['valide'])
            ->whereDate('updated_at', $dateStr)
            ->sum('montant');

        $soldeTheorique = (int) ($fondOuverture + $totalEncaissements - $totalDecaissements);

        return [$totalEncaissements, $totalDecaissements, $soldeTheorique];
    }

    private function getFondOuverture(Carbon $date): int
    {
        $dateStr = $date->toDateString();

        // Si le responsable a déjà défini le fond pour ce jour (journal existant), l'utiliser
        $journalDuJour = CaissierCaisseJournal::where('date', $dateStr)->first();
        if ($journalDuJour !== null) {
            return (int) ($journalDuJour->fond_ouverture ?? 0);
        }

        // Sinon : fond = solde de clôture de la veille
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

