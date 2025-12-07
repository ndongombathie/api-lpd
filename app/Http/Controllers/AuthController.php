<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INSCRIPTION
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nom'         => 'required|string',
            'prenom'      => 'required|string',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:6',
            'role'        => 'required|string',
            'boutique_id' => 'nullable|uuid',
            'adresse'     => 'nullable|string',
            'numero_cni'  => 'nullable|string',
            'telephone'   => 'nullable|string',
        ]);

        $user = User::create([
            'nom'          => $validated['nom'],
            'prenom'       => $validated['prenom'],
            'email'        => $validated['email'],
            'password'     => Hash::make($validated['password']),
            'role'         => $validated['role'],
            'boutique_id'  => $validated['boutique_id'] ?? null,
            'adresse'      => $validated['adresse'] ?? null,
            'numero_cni'   => $validated['numero_cni'] ?? null,
            'telephone'    => $validated['telephone'] ?? null,
            // présence : nouvel utilisateur créé mais pas "connecté"
            'is_online'    => false,
            'last_login_at'=> null,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | CONNEXION
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        /** @var \App\Models\User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        // 🟢 On marque le user comme "en ligne"
        $user->is_online     = true;
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DÉCONNEXION
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user) {
            // 🔴 On marque l'utilisateur hors ligne
            $user->is_online = false;
            $user->save();

            // ✅ On supprime le token courant (Sanctum)
            /** @var PersonalAccessToken|null $token */
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            }
        }

        return response()->json([
            'message' => 'Déconnecté',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGER MOT DE PASSE
    |--------------------------------------------------------------------------
    */
    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'L’ancien mot de passe est incorrect.',
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }
}
