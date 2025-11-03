<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    public function index(string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);
        return Paiement::where('commande_id', $commande->id)->orderBy('date')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $commandeId)
    {
        $commande = Commande::findOrFail($commandeId);
        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'type_paiement' => 'required|string',
        ]);

        return DB::transaction(function () use ($commande, $data) {
            $totalPaye = Paiement::where('commande_id', $commande->id)->sum('montant');
            $reste = max(0, $commande->total - $totalPaye - $data['montant']);

            $paiement = Paiement::create([
                'commande_id' => $commande->id,
                'montant' => $data['montant'],
                'type_paiement' => $data['type_paiement'],
                'date' => now(),
                'reste_du' => $reste,
            ]);

            if ($reste <= 0) {
                $commande->update(['statut' => 'payee']);
            }

            return $paiement;
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Paiement::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(405);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort(405);
    }
}
