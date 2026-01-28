<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCredentialsMail;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::query()->latest();
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
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            return $query->paginate(20);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des utilisateurs',
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
                'adresse' => 'nullable|string',
                'numero_cni' => 'nullable|string',
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
}
