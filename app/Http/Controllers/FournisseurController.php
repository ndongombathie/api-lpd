<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    /**
     * GET /api/fournisseurs
     * Pagination Laravel pour le Responsable
     */
    public function index()
    {
        return Fournisseur::query()
            ->latest()
            ->paginate(20);
    }

    /**
     * POST /api/fournisseurs
     * Création fournisseur (Responsable)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'contact' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:255',

            // Champs métier Responsable
            'type_produit' => 'nullable|string|max:255',
            'derniere_livraison' => 'nullable|date',

            // Champ stock (Gestionnaire dépôt)
            'total_achats' => 'nullable|numeric|min:0',
        ]);

        // valeur par défaut si non fournie
        if (!isset($data['total_achats'])) {
            $data['total_achats'] = 0;
        }

        $row = Fournisseur::create($data);

        return response()->json($row, 201);
    }

    /**
     * GET /api/fournisseurs/{id}
     */
    public function show(string $id)
    {
        return Fournisseur::findOrFail($id);
    }

    /**
     * PUT /api/fournisseurs/{id}
     * Modification fournisseur
     */
    public function update(Request $request, string $id)
    {
        $row = Fournisseur::findOrFail($id);

        $data = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'contact' => 'nullable|string|max:50',
            'adresse' => 'nullable|string|max:255',

            // Champs Responsable
            'type_produit' => 'nullable|string|max:255',
            'derniere_livraison' => 'nullable|date',

            // Champ stock
            'total_achats' => 'nullable|numeric|min:0',
        ]);

        $row->update($data);

        return $row;
    }

    /**
     * DELETE /api/fournisseurs/{id}
     */
    public function destroy(string $id)
    {
        $row = Fournisseur::findOrFail($id);
        $row->delete();

        return response()->noContent();
    }
}
