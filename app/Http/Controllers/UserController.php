<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use Illuminate\Support\Facades\Auth;
use App\Models\Commande;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::query()
            ->orderBy('created_at', 'desc')
            ->latest();
            // Filter by role if provided
            if ($request->filled('role')) {
                $query->where('role', $request->input('role'));
            }

            // Filter by boutique_id if provided
            if ($request->filled('boutique_id')) {
                $query->where('boutique_id', $request->input('boutique_id'));
            }

            // Filter by search term if provided
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    // 🔹 recherche prénom+nom ou nom+prenom
                    ->orWhereRaw("CONCAT(prenom, ' ', nom) LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(nom, ' ', prenom) LIKE ?", ["%{$search}%"]);
                });
            }
            return $query->paginate(10);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des utilisateurs',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    #Nombre total de vendeurs
    public function vendeursCount()
    {
        try {
            return response()->json([
                'vendeurs_count' => User::where('role', 'vendeur')->count(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du nombre de vendeurs',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    #nombre total de caissier
    public function caissiersCount()
    {
        try {
            return response()->json([
                'caissiers_count' => User::where('role', 'caissier')->count(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du nombre de caissiers',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    # nombre total de gestionnaire boutique
    public function gestionnairesCount()
    {
        try {
            return response()->json([
                'gestionnaires_count' => User::where('role', 'gestionnaire_boutique')->count(),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération du nombre de gestionnaires boutique',
                'error' => $th->getMessage()
            ], 500);
        }
    }



    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'adresse' => 'required|string',
                'numero_cni' => 'required|string',
                'telephone' => 'nullable|string',
                'role' => 'required|string',
                'email' => 'required|email|unique:users,email',
            ]);

            $plainPassword = $data['nom']."124";
            $data['password']=bcrypt($plainPassword);
            $data['boutique_id']=Auth::user()->id;
            $user = User::create($data);

            // Envoyer les identifiants par email
            try {
                Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));
                logger()->info('Identifiants de connexion envoyés par e-mail à l\'utilisateur ' . $user->email);
            } catch (\Throwable $mailEx) {
                // On n'échoue pas la création de l'utilisateur si l'email ne part pas,
                // mais on retourne l'info dans la réponse
                return response()->json([
                    'user' => $user,
                    'warning' => 'Utilisateur créé, mais e-mail non envoyé: ' . $mailEx->getMessage(),
                ], 201);
            }
            return response()->json($user, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'utilisateur',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            return User::findOrFail($id);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération de l\'utilisateur',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update($request->all());
            return response()->json($user);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de l\'utilisateur',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
            try {
                $user = User::findOrFail($id);
                $user->delete();
                return response()->noContent();

            return response()->json([
                'message' => 'Utilisateur supprimé avec succès'
            ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'message' => 'Erreur lors de la suppression de l\'utilisateur',
                    'error' => $th->getMessage()
                ], 500);
            }
    }

    public function vendeursStats()
    {
        try {

            $vendeurs = User::where('role', 'vendeur')
                ->with(['ventes' => function ($q) {
                    $q->where('statut', 'soldee');
                }])
                ->get()
                ->map(function ($u) {

                    $totalVentes = $u->ventes->count();
                    $montantTotal = $u->ventes->sum('total');

                    return [
                        'id' => $u->id,
                        'name' => $u->prenom . ' ' . $u->nom,
                        'email' => $u->email,
                        'status' => 'actif',
                        'stats' => [
                            'totalVentes' => $totalVentes,
                            'montantTotal' => $montantTotal,
                        ]
                    ];
                });

            return response()->json($vendeurs);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur récupération vendeurs',
                'error' => $th->getMessage()
            ], 500);
        }
    }
public function caissiersStats()
{
    try {

        // ✅ TOTAL CAISSE GLOBAL (cartes du haut)
        $encaissementsTotal = \App\Models\Paiement::sum('montant');

        $caissiers = User::where('role', 'caissier')
            ->get()
            ->map(function ($u) {

                // ✅ uniquement décaissements validés
                $decaissements = \App\Models\Decaissement::where('caissier_id', $u->id)
                    ->where('statut', 'valide')
                    ->sum('montant_total');

                return [
                    'id' => $u->id,
                    'name' => $u->prenom.' '.$u->nom,
                    'email' => $u->email,
                    'status' => 'actif',
                    'stats' => [
                        // ⚠️ pas d'encaissement individuel pour l’instant
                        'encaissementsTotal' => 0,
                        'decaissementsTotal' => (int) $decaissements,
                        'soldeNet' => -(int) $decaissements,
                        'fondOuverture' => 0,
                    ]
                ];
            });

        return response()->json([
            'encaissementsGlobal' => (int) $encaissementsTotal,
            'data' => $caissiers
        ]);

    } catch (\Throwable $th) {
        return response()->json([
            'message' => 'Erreur récupération caissiers',
            'error' => $th->getMessage()
        ], 500);
    }
}

 public function resetPassword(string $id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update([
                'password' => bcrypt($user->nom."124")
            ]);
            $plainPassword = $user->nom."124";
            Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès'
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
