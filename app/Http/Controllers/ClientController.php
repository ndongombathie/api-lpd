<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Liste des clients (avec filtre optionnel type_client).
     *
     * GET /api/clients
     * GET /api/clients?type_client=special
     * GET /api/clients?type_client=normal
     * GET /api/clients?type_client=classique
     */
    public function index(Request $request)
    {
        $query = Client::query()->orderByDesc('created_at');

        // Filtre éventuel par type_client
        if ($request->filled('type_client')) {
            $query->where('type_client', $request->type_client);
        }

        // 🔹 On renvoie une simple liste, pas de pagination,
        //    comme ça ton front peut lire res.data directement.
        return response()->json($query->get(), 200);
    }

    /**
     * Création d’un client.
     *
     * - type_client = special   → prénom / CNI / téléphone optionnels
     * - type_client = normal|classique ou absent → client "classique"
     */
    public function store(Request $request)
    {
        try {
            // valeur par défaut : "normal" pour rester compatible
            $type = $request->input('type_client', 'normal');

            // Règles communes
            $baseRules = [
                'nom'        => 'required|string|max:255',
                'entreprise' => 'required|string|max:255',
                'adresse'    => 'required|string|max:255',
                'contact'    => 'nullable|string|max:255',
                'type_client'=> ['nullable', Rule::in(['normal', 'classique', 'special'])],
                'solde'      => 'nullable|numeric',
            ];

            // Règles spécifiques
            if ($type === 'special') {
                // 🟢 Client spécial : tout ça est optionnel
                $extraRules = [
                    'prenom'     => 'nullable|string|max:255',
                    'numero_cni' => 'nullable|string|max:255',
                    'telephone'  => 'nullable|string|max:255',
                ];
            } else {
                // 🔴 Client classique / normal :
                $extraRules = [
                    'prenom'     => 'required|string|max:255',
                    'numero_cni' => 'required|string|max:255|unique:clients,numero_cni',
                    'telephone'  => 'required|string|max:255',
                ];
            }

            $validated = $request->validate($baseRules + $extraRules);

            // Si type_client n’a pas été envoyé, on force ce qu’on a déduit
            if (empty($validated['type_client'])) {
                $validated['type_client'] = $type;
            }

            // Solde par défaut
            $validated['solde'] = $validated['solde'] ?? 0;

            $client = Client::create($validated);

            return response()->json($client, 201);
        } catch (\Throwable $e) {
            // Log complet côté Laravel pour qu’on puisse débugger si besoin
            Log::error('Erreur création client', [
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
                'request'   => $request->all(),
            ]);

            return response()->json([
                'message' => 'Erreur interne lors de la création du client.',
            ], 500);
        }
    }

    /**
     * Affichage d’un client.
     */
    public function show(string $id)
    {
        return Client::findOrFail($id);
    }

    /**
     * Mise à jour d’un client.
     */
    public function update(Request $request, string $id)
    {
        $client = Client::findOrFail($id);

        $data = $request->validate([
            'nom'        => 'sometimes|string|max:255',
            'prenom'     => 'sometimes|nullable|string|max:255',
            'entreprise' => 'sometimes|nullable|string|max:255',
            'adresse'    => 'sometimes|nullable|string|max:255',
            'numero_cni' => 'sometimes|nullable|string|max:255',
            'telephone'  => 'sometimes|nullable|string|max:255',
            'type_client'=> ['sometimes', 'nullable', Rule::in(['normal', 'classique', 'special'])],
            'solde'      => 'sometimes|nullable|numeric',
            'contact'    => 'sometimes|nullable|string|max:255',
        ]);

        $client->update($data);

        return $client;
    }

    /**
     * Suppression d’un client.
     */
    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);
        $client->delete();

        return response()->noContent();
    }
}
