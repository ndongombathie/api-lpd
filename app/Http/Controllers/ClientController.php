<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return Client::query()->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type_client' => 'required|in:normal,special',
            'solde' => 'nullable|numeric',
            'contact' => 'nullable|string',
        ]);
        $client = Client::create($data);
        return response()->json($client, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Client::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Client::findOrFail($id);
        $data = $request->validate([
            'nom' => 'sometimes|string',
            'prenom' => 'sometimes|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type_client' => 'sometimes|in:normal,special',
            'solde' => 'nullable|numeric',
            'contact' => 'nullable|string',
        ]);
        $client->update($data);
        return $client;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);
        $client->delete();
        return response()->noContent();
    }

    /**
     * Liste des paiements par tranches pour un client et les soldes restants.
     */
    public function paiementsTranches(Request $request, string $clientId)
    {
        $client = Client::findOrFail($clientId);

        // Commandes du client avec paiements (tranches)
        $paginator = Commande::with(['paiements' => function ($q) {
                $q->orderBy('date');
            }])
            ->where('client_id', $client->id)
            ->orderByDesc('date')
            ->paginate(50);

        // Transformer chaque commande pour inclure total payé et reste
        $transformed = $paginator->getCollection()->map(function (Commande $cmd) {
            $totalPaye = $cmd->paiements->sum('montant');
            $reste = max(0, $cmd->total - $totalPaye);
            return [
                'commande_id' => $cmd->id,
                'date' => $cmd->date,
                'statut' => $cmd->statut,
                'total' => $cmd->total,
                'total_paye' => $totalPaye,
                'reste' => $reste,
                'tranches' => $cmd->paiements->map(function (Paiement $p) {
                    return [
                        'paiement_id' => $p->id,
                        'montant' => $p->montant,
                        'type_paiement' => $p->type_paiement,
                        'date' => $p->date,
                        'reste_du' => $p->reste_du,
                    ];
                }),
            ];
        });
        $paginator->setCollection($transformed);

        // Résumé global pour le client (toutes commandes)
        $commandeIds = Commande::where('client_id', $client->id)->pluck('id');
        $montantTotal = Commande::where('client_id', $client->id)->sum('total');
        $totalPayeGlobal = Paiement::whereIn('commande_id', $commandeIds)->sum('montant');
        $resteTotal = max(0, $montantTotal - $totalPayeGlobal);

        return response()->json([
            'client' => [
                'id' => $client->id,
                'nom' => $client->nom,
                'prenom' => $client->prenom,
                'type_client' => $client->type_client,
            ],
            'summary' => [
                'commandes_count' => $commandeIds->count(),
                'montant_total' => $montantTotal,
                'total_paye' => $totalPayeGlobal,
                'reste_total' => $resteTotal,
            ],
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
