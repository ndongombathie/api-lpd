<?php

    namespace App\Http\Controllers;

    use App\Models\Client;
    use App\Models\Commande;
    use App\Models\Paiement;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;

    class ClientController extends Controller
    {
        // ============================================================
        // LISTE DES CLIENTS
        // ============================================================
        public function index(Request $request)
        {
            $query = Client::query()->latest();

            if ($request->filled('type_client')) {
                $query->where('type_client', $request->type_client);
            }

            return $query->paginate(20);
        }

        // ============================================================
        // CRÉATION
        // ============================================================
        public function store(Request $request)
        {
            $isResponsable = Auth::user()->role === 'responsable';

            $data = $request->validate([
                'nom'        => 'required|string',
                'entreprise' => 'nullable|string',
                'prenom'     => $isResponsable ? 'nullable|string' : 'required|string',
                'adresse'    => 'nullable|string',
                'numero_cni' => 'nullable|string',
                'telephone' => 'nullable|string',
                'contact'    => 'nullable|string',
                'solde'      => 'nullable|numeric',
            ]);
            $data['entreprise'] = $data['entreprise'] ?? null;

            // Règle métier
            $data['type_client'] = $isResponsable ? 'special' : 'normal';

            // Si responsable → on force prenom = null
            if ($isResponsable) {
                $data['prenom'] = null;
            }

            $client = Client::create($data);

            return response()->json($client, 201);
        }

        // ============================================================
        // AFFICHER
        // ============================================================
        public function show(string $id)
        {
            return Client::findOrFail($id);
        }

        // ============================================================
        // MODIFICATION
        // ============================================================
        public function update(Request $request, string $id)
        {
            $client = Client::findOrFail($id);
            $isResponsable = Auth::user()->role === 'responsable';

            $data = $request->validate([
                'nom'        => 'sometimes|string',
                'entreprise' => 'nullable|string',
                'prenom'     => 'nullable|string',
                'adresse'    => 'nullable|string',
                'numero_cni' => 'nullable|string',
                'telephone' => 'nullable|string',
                'contact'    => 'nullable|string',
                'solde'      => 'nullable|numeric',
            ]);
            $data['entreprise'] = $data['entreprise'] ?? null;

            // Le responsable ne doit pas renseigner prenom
            if ($isResponsable) {
                $data['prenom'] = null;
            }

            // Seul le responsable peut changer type_client
            if ($isResponsable && $request->filled('type_client')) {
                $client->type_client = $request->type_client;
            }

            $client->update($data);

            return $client;
        }

        // ============================================================
        // SUPPRESSION
        // ============================================================
        public function destroy(string $id)
        {
            $client = Client::findOrFail($id);

            // ✅ On applique la règle seulement aux clients spéciaux
            if ($client->type_client === 'special') {

                $hasDebt = $client->commandes()
                    ->where('statut', '!=', 'annulee')
                    ->whereRaw("
                        total > (
                            SELECT COALESCE(SUM(montant),0)
                            FROM paiements
                            WHERE paiements.commande_id = commandes.id
                        )
                    ")
                    ->exists();

                if ($hasDebt) {
                    return response()->json([
                        'message' => "Impossible de supprimer ce client : il a encore une dette."
                    ], 422);
                }
            }

            $client->delete();

            return response()->noContent();
        }


        // ============================================================
        // TRANCHES
        // ============================================================
        public function paiementsTranches(Request $request, string $clientId)
        {
            $client = Client::findOrFail($clientId);

            $paginator = Commande::with(['paiements' => function ($q) {
                    $q->orderBy('date');
                }])
                ->where('client_id', $client->id)
                ->orderByDesc('date')
                ->paginate(50);

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

            $commandeIds = Commande::where('client_id', $client->id)->pluck('id');
            $montantTotal = Commande::where('client_id', $client->id)->sum('total');
            $totalPayeGlobal = Paiement::whereIn('commande_id', $commandeIds)->sum('montant');
            $resteTotal = max(0, $montantTotal - $totalPayeGlobal);

            return response()->json([
                'client' => [
                    'id' => $client->id,
                    'nom' => $client->nom,
                    'entreprise' => $client->entreprise,
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
