<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    /**
     * Liste des fournisseurs de la boutique du responsable connecté.
     */
    public function index(Request $request)
    {
        $boutiqueId = $request->user()->boutique_id;

        $fournisseurs = Fournisseur::where('boutique_id', $boutiqueId)
            ->orderBy('nom')
            ->get();

        return response()->json($fournisseurs);
    }

    /**
     * Création d’un fournisseur.
     */
    public function store(Request $request)
    {
        $boutiqueId = $request->user()->boutique_id;

        $validated = $request->validate([
            'nom'               => 'required|string|max:255',
            'contact'           => 'nullable|string|max:20',
            'adresse'           => 'nullable|string|max:255',
            'type_produit'      => 'required|string|max:255',
            'derniere_livraison'=> 'nullable|date',
        ]);

        $fournisseur = Fournisseur::create([
            'boutique_id'        => $boutiqueId,
            'nom'                => $validated['nom'],
            'contact'            => $validated['contact'] ?? null,
            'adresse'            => $validated['adresse'] ?? null,
            'type_produit'       => $validated['type_produit'],
            'derniere_livraison' => $validated['derniere_livraison'] ?? null,
            'total_achats'       => 0,
        ]);

        return response()->json($fournisseur, 201);
    }

    /**
     * Mise à jour d’un fournisseur.
     */
    public function update(Request $request, Fournisseur $fournisseur)
    {
        // Vérifier que le fournisseur appartient bien à la boutique du responsable
        if ($fournisseur->boutique_id !== $request->user()->boutique_id) {
            return response()->json(['message' => 'Accès interdit.'], 403);
        }

        $validated = $request->validate([
            'nom'               => 'sometimes|required|string|max:255',
            'contact'           => 'nullable|string|max:20',
            'adresse'           => 'nullable|string|max:255',
            'type_produit'      => 'sometimes|required|string|max:255',
            'derniere_livraison'=> 'nullable|date',
        ]);

        $fournisseur->fill($validated);
        $fournisseur->save();

        return response()->json($fournisseur);
    }

    /**
     * Suppression d’un fournisseur.
     */
    public function destroy(Request $request, Fournisseur $fournisseur)
    {
        if ($fournisseur->boutique_id !== $request->user()->boutique_id) {
            return response()->json(['message' => 'Accès interdit.'], 403);
        }

        $fournisseur->delete();

        return response()->json(null, 204);
    }
}
