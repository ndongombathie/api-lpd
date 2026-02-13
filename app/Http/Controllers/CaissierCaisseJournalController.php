<?php

namespace App\Http\Controllers;

use App\Models\CaissierCaisseJournal;
use App\Models\Decaissement;
use App\Models\fondCaisse;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    #toutes les caisserjounal et filter par date mettre la date par defaut a la date d'aujourd'hui
    public function all(Request $request)
    {
        try {
            $query = CaissierCaisseJournal::query()->with('caissier')->orderByDesc('date');

            if (!$request->filled('date_debut')) {
                $request->merge(['date_debut' => Carbon::today()->toDateString()]);
            }
            if (!$request->filled('date_fin')) {
                $request->merge(['date_fin' => Carbon::today()->toDateString()]);
            }

            if ($request->filled('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            }
            if ($request->filled('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            $journals = $query->paginate(10);

        return response()->json($journals);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }



    public function show(string $date)
    {
        $dateStr = Carbon::parse($date)->toDateString();

        $journal = CaissierCaisseJournal::where('date', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->first();

        if (!$journal) {
            [$totalEncaissements, $totalDecaissements, $soldeTheorique,$nombrePaiements] = [0, 0, 0, 0];
            return response()->json([
                'date' => $dateStr,
                'fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr)),
                'total_encaissements' => $totalEncaissements ?? 0,
                'total_decaissements' => $totalDecaissements ?? 0,
                'nombre_paiements'=>$nombrePaiements ?? 0,
                'solde_theorique' => $soldeTheorique ?? 0,
            ]);

        } else {
            [$totalEncaissements, $totalDecaissements, $soldeTheorique,$nombrePaiements] = $this->computeTotals($dateStr, (int) $this->getFondOuverture(Carbon::parse($dateStr)));
        }


        // Mettre à jour les totaux théoriques (sans écraser solde_reel/observations)
        $journal->fill([
            'fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr)),
            'total_encaissements' => $totalEncaissements ?? 0,
            'total_decaissements' => $totalDecaissements ?? 0,
            'nombre_paiements'=>$nombrePaiements ?? 0,
            'solde_theorique' => $soldeTheorique ?? 0,
        ])->save();

        return response()->json($journal->fresh());
    }

    #filter par date mettre la date par defaut a la date d'aujourd'hui si la paremtre n'est pas fourni
    public function total_encaissement(Request $request)
    {
        try {

            $query = Paiement::query();

            if (!$request->filled('date_debut')) {
                $request->merge(['date_debut' => Carbon::today()->toDateString()]);
            }
            if (!$request->filled('date_fin')) {
                $request->merge(['date_fin' => Carbon::today()->toDateString()]);
            }

            if ($request->filled('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            }
            if ($request->filled('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            $totalEncaissements = (int) $query->sum('montant');

            return response()->json($totalEncaissements);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function total_decaissement(Request $request)
    {
        try {

            $query = Decaissement::query();

            if (!$request->filled('date_debut')) {
                $request->merge(['date_debut' => Carbon::today()->toDateString()]);
            }
            if (!$request->filled('date_fin')) {
                $request->merge(['date_fin' => Carbon::today()->toDateString()]);
            }

            if ($request->filled('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            }
            if ($request->filled('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            $totalDecaissements = (int) $query->sum('montant');

            return response()->json($totalDecaissements);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function total_caisse(Request $request)
    {
        $query = CaissierCaisseJournal::query();

        if (!$request->filled('date_debut')) {
            $request->merge(['date_debut' => Carbon::today()->toDateString()]);
        }
        if (!$request->filled('date_fin')) {
            $request->merge(['date_fin' => Carbon::today()->toDateString()]);
        }

        if ($request->filled('date_debut')) {
            $query->where('date', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->where('date', '<=', $request->date_fin);
        }

        $dateStr = Carbon::parse($request->date_debut)->toDateString();

        $totalCaisse = (int) $query->whereDate('date', $dateStr)->sum('solde_theorique');

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
            ['fond_ouverture' => $this->getFondOuverture(Carbon::parse($data['date']))]
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

        $journal = CaissierCaisseJournal::where('date', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->first();

        if (!$journal) {
            [$totalEncaissements, $totalDecaissements, $soldeTheorique, $nombrePaiements] = [0, 0, 0, 0];
        }

        [$totalEncaissements, $totalDecaissements, $soldeTheorique, $nombrePaiements] = $this->computeTotals($dateStr, (int) $this->getFondOuverture(Carbon::parse($dateStr)));

        // Mettre à jour les totaux réels et autres informations
        $journal->fill([
            'fond_ouverture' => $this->getFondOuverture(Carbon::parse($dateStr)),
            'total_encaissements' => $totalEncaissements ?? 0,
            'total_decaissements' => $totalDecaissements ?? 0,
            'nombre_paiements' => $nombrePaiements ?? 0,
            'caissier_id' => Auth::user()->id,
            'solde_theorique' => $soldeTheorique ?? 0,
            'solde_reel' => (int) $data['solde_reel'] ?? 0,
            'observations' => $data['observations'] ?? null,
            'cloture' => true,
        ])->save();

        return response()->json($journal->fresh());
    }

    private function computeTotals(string $dateStr, int $fondOuverture): array
    {
        $totalEncaissements = (int) Paiement::whereDate('date', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->sum('montant');
        $nombrePaiements = (int) Paiement::whereDate('date', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->count();

        $totalDecaissements = (int) Decaissement::whereRaw('LOWER(statut) = ?', ['valide'])
            ->whereDate('updated_at', $dateStr)
            ->where('caissier_id', Auth::user()->id)
            ->sum('montant');

        $soldeTheorique = (int) ($fondOuverture + $totalEncaissements - $totalDecaissements);

        return [$totalEncaissements, $totalDecaissements, $soldeTheorique, $nombrePaiements];
    }

    private function getFondOuverture(Carbon $date): int
    {
        $veille = $date->copy()->subDay()->toDateString();

        $rapportVeille = fondCaisse::where('date', $veille)
            ->where('caissier_id', Auth::user()->id)
            ->get('montant')
            ->first();

        if (!$rapportVeille) {
            return 0;
        }

        return (int) ($rapportVeille->montant ?? 0);
    }
}

