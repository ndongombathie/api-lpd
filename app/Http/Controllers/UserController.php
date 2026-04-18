<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use Illuminate\Support\Facades\Auth;
use App\Models\Commande;
use Illuminate\Support\Str;
//la fonction hash sha256
use Illuminate\Support\Facades\Hash;

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
                    ->orWhere('email_hash', hash('sha256', $search))
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
                'nom' => 'required|string|max:100',
                'prenom' => 'required|string|max:100',
                'adresse' => 'required|string|max:255',
                'numero_cni' => 'required|string|max:50',
                'telephone' => 'nullable|string|max:20',
                'role' => 'required|string',
                'email' => 'required|email|unique:users,email',
            ]);

            // 🔐 Normalisation
            $data['email'] = strtolower(trim($data['email']));
            $data['numero_cni'] = trim($data['numero_cni']);

            // 🔐 Hash pour recherche
            $data['numero_cni_hash'] = hash('sha256', $data['numero_cni']);
            $data['email_hash'] = hash('sha256', $data['email']);
            $data['adresse_hash'] = hash('sha256', $data['adresse']);

            $data['telephone_hash'] = !empty($data['telephone'])
                ? hash('sha256', $data['telephone'])
                : null;

            // 🔑 Mot de passe sécurisé
            $plainPassword = Str::random(10);
            $data['password'] = Hash::make($plainPassword);

            $data['boutique_id'] = Auth::user()->boutique_id;

            $user = User::create($data);

            Mail::to($user->email)->send(new UserCredentialsMail($user, $plainPassword));
            return response()->json([
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
            ], 201);

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
            $data = $request->all();
            $data['telephone_hash'] = hash('sha256', $data['telephone']);
            $data['adresse_hash'] = hash('sha256', $data['adresse']);
            $user->update($data);
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
            $password = 'lpdpassword';
            $user->update([
                'password' => bcrypt($password)
            ]);
            Mail::to($user->email)->send(new UserCredentialsMail($user, $password));
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
    public function allCaissiers()
    {
        try {
            return User::where('role', 'caissier')
                ->orderBy('prenom')
                ->get();
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur récupération caissiers',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
