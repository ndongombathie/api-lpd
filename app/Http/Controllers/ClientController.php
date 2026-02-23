<?php

    namespace App\Http\Controllers;

    use App\Models\Client;
    use App\Models\Commande;
    use App\Models\Paiement;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Auth;


    class ClientController extends Controller
    {
        private function normalizeContact(?string $contact): ?string
        {
            if (!$contact) return null;

            // garder uniquement les chiffres
            $contact = preg_replace('/\D/', '', $contact);

            // retirer indicatif Sénégal si présent
            if (str_starts_with($contact, '221')) {
                $contact = substr($contact, -9);
            }

            return $contact;
        }

        // ============================================================
        // LISTE DES CLIENTS
        public function index(Request $request)
        {
            $query = Client::withCount('commandes')->latest();

            // filtre type client
            if ($request->filled('type_client')) {
                $query->where('type_client', $request->type_client);
            }

            // ✅ FILTRE RECHERCHE (MANQUANT)
            if ($request->filled('search')) {
                $s = $request->search;

                $query->where(function ($q) use ($s) {
                    $q->where('nom', 'like', "%{$s}%")
                    ->orWhere('contact', 'like', "%{$s}%")
                    ->orWhere('entreprise', 'like', "%{$s}%")
                    ->orWhere('adresse', 'like', "%{$s}%");
                });
            }

            return $query->paginate(10);
        }

        // ============================================================
        // CRÉATION
        // ============================================================
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

            if(Auth::user()->role == 'responsable')
            {
                $data['type_client'] = 'special';
            }
            else
            {
                $data['type_client'] = 'normal';
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
            $request->merge([
                'contact' => $this->normalizeContact($request->contact),
            ]);

            $client = Client::findOrFail($id);
            $isResponsable = Auth::user()->role === 'responsable';

            $data = $request->validate([
                'nom'        => 'sometimes|string',
                'entreprise' => 'nullable|string',
                'prenom'     => 'nullable|string',
                'adresse'    => 'nullable|string',
                'numero_cni' => 'nullable|string',
                'telephone' => 'nullable|string',
                'contact' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) use ($client, $isResponsable) {

                        if ($isResponsable && $value) {

                            $exists = Client::where('contact', $value)
                                ->where('type_client', 'special')
                                ->where('id', '!=', $client->id)
                                ->exists();

                            if ($exists) {
                                $fail('Un client spécial avec ce numéro existe déjà.');
                            }
                        }
                    },
                ],

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
                    ->whereNotNull('total')
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
            $totalPaye = $cmd->montantPaye();
            $reste = $cmd->resteAPayer();


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

        public function stats()
        {
            return response()->json([
                'total' => Client::count(),
                'speciaux' => Client::where('type_client', 'special')->count(),
                'normaux' => Client::where('type_client', 'normal')->count(),
            ]);
        }
        // ============================================================
        // LISTE COMPLETE CLIENTS SPECIAUX (sans pagination)
        // ============================================================
        public function allSpeciaux()
        {
            return Client::where('type_client', 'special')
                ->orderBy('nom')
                ->get();
        }

           #la liste des clients en dette
           public function clientsDette()
           {
            try {
               $clients = Client::where('type_client', 'special')
                   ->where('statut','en_dette')
                   ->paginate(10);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Erreur lors de la récupération des clients en dette: ' . $e->getMessage(),
                ], 500);
            }
               return $clients;
           }
    }



