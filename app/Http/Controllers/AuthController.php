<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|string',
            'boutique_id' => 'nullable|uuid',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|string',
            'telephone' => 'nullable|string',
        ]);

        $user = User::create([
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'boutique_id' => $validated['boutique_id'] ?? null,
            'adresse' => $validated['adresse'] ?? null,
            'numero_cni' => $validated['numero_cni'] ?? null,
            'telephone' => $validated['telephone'] ?? null,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        

        $user = User::where('email', $credentials['email'])->first();
       
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }
        
       
        $token = $user->createToken('api')->plainTextToken;
       
        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté']);
    }

    public function monProfil(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfil(Request $request)
    {
        $request->user()->update($request->all());
        return response()->json($request->user());
    }
}
