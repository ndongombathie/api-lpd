<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    public function index()
    {
        return Fournisseur::query()->latest()->paginate(20);
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
        return Fournisseur::findOrFail($id);
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
