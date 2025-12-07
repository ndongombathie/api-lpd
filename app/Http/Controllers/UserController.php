<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs de la même boutique que le responsable.
     * 👉 Lecture seule pour le module Responsable.
     */
    public function index(Request $request)
    {
        $auth = $request->user();

        $users = User::with('boutique')
            ->where('boutique_id', $auth->boutique_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    /**
     * Détail d'un user.
     * Route model binding via apiResource → /api/users/{user}
     */
    public function show(Request $request, User $user)
    {
        // Vérifier qu'il est bien dans la même boutique
        if ($user->boutique_id !== $request->user()->boutique_id) {
            return response()->json([
                'message' => 'Accès interdit.',
            ], 403);
        }

        return response()->json($user->load('boutique'));
    }

    /**
     * Création d'un utilisateur — NON AUTORISÉ pour le Responsable.
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Création d’utilisateur non autorisée pour ce profil.',
        ], 403);
    }

    /**
     * Mise à jour d'un utilisateur — NON AUTORISÉ pour le Responsable.
     */
    public function update(Request $request, User $user)
    {
        return response()->json([
            'message' => 'Modification d’utilisateur non autorisée pour ce profil.',
        ], 403);
    }

    /**
     * Suppression d'un utilisateur — NON AUTORISÉ pour le Responsable.
     */
    public function destroy(Request $request, User $user)
    {
        return response()->json([
            'message' => 'Suppression d’utilisateur non autorisée pour ce profil.',
        ], 403);
    }
}
