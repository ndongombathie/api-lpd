<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        try {
            return User::query()->latest()->paginate(20);
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
                'boutique_id' => 'required|uuid|exists:boutiques,id',
                'nom' => 'required|string',
                'prenom' => 'required|string',
                'adresse' => 'nullable|string',
                'numero_cni' => 'nullable|string',
                'telephone' => 'nullable|string',
                'role' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);
            $user = User::create($data);
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
