<?php

namespace App\Http\Controllers;

use App\Models\CaissierCaisseJournal;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Endpoints pour le fond d'ouverture de caisse.
 * Le responsable définit le montant ; le caissier l'affiche (via dashboard / rapport).
 */
class FondOuvertureController extends Controller
{
    /**
     * Récupérer le fond d'ouverture pour une date.
     * GET /api/fond-ouverture?date=Y-m-d
     */
    public function show(Request $request)
    {
        $dateStr = $request->query('date', now()->toDateString());
        $date = Carbon::parse($dateStr)->toDateString();

        $journal = CaissierCaisseJournal::where('date', $date)->first();

        $fondOuverture = $journal
            ? (int) ($journal->fond_ouverture ?? 0)
            : $this->getFondOuvertureVeille($date);

        return response()->json([
            'date' => $date,
            'fond_ouverture' => $fondOuverture,
        ]);
    }

    /**
     * Définir le fond d'ouverture pour une date (responsable).
     * PUT /api/fond-ouverture
     * Body: { "date": "Y-m-d", "montant": 12345 }
     */
    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['responsable', 'admin'], true)) {
            return response()->json(['message' => 'Seul le responsable peut définir le fond d\'ouverture.'], 403);
        }

        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'montant' => 'required|numeric|min:0',
        ]);

        $journal = CaissierCaisseJournal::updateOrCreate(
            ['date' => $data['date']],
            ['fond_ouverture' => (int) $data['montant']]
        );

        return response()->json([
            'date' => $journal->date->format('Y-m-d'),
            'fond_ouverture' => (int) $journal->fond_ouverture,
        ]);
    }

    private function getFondOuvertureVeille(string $dateStr): int
    {
        $date = Carbon::parse($dateStr);
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
