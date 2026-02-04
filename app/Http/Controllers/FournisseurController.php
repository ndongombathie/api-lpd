<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Fournisseur::query()->latest();

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('contact', 'like', "%{$search}%")
                      ->orWhere('adresse', 'like', "%{$search}%");
                });
            }

            return response()->json($query->paginate(20));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'contact' => 'nullable|string',
            'adresse' => 'nullable|string',
            'total_achats' => 'nullable|numeric',
        ]);
        $row = Fournisseur::create($data);
        return response()->json($row, 201);
    }

    public function show(string $id)
    {
        try {
            return response()->json(Fournisseur::with('produits')->findOrFail($id));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $row = Fournisseur::findOrFail($id);
        $data = $request->validate([
            'nom' => 'sometimes|string',
            'contact' => 'nullable|string',
            'adresse' => 'nullable|string',
            'total_achats' => 'nullable|numeric',
        ]);
        $row->update($data);
        return $row;
    }

    public function destroy(string $id)
    {
        $row = Fournisseur::findOrFail($id);
        $row->delete();
        return response()->noContent();
    }
}
