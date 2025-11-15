<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'prenom' => 'required|string|max:255',
            'nom' => 'required|string|max:255',
            'photo' => 'nullable|string' // Base64 ou URL
        ]);

        $user = $request->user();

        $user->prenom = $request->prenom;
        $user->nom = $request->nom;
        $user->photo = $request->photo; // ATTENTION : ajoute cette colonne en DB si elle n'existe pas

        $user->save();

        return response()->json($user);
    }
}
