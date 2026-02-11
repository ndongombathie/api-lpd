<?php

namespace App\Http\Controllers;

use App\Models\Inventaire;
use App\Http\Requests\StoreInventaireRequest;
use App\Http\Requests\UpdateInventaireRequest;
use Illuminate\Http\Request;


class InventaireController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Inventaire::query()->latest();
            // Filter by type if provided
            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }
            
            // Filter by search term if provided
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('date', 'like', "%{$search}%")
                      ->orWhere('prix_achat_total', 'like', "%{$search}%")
                      ->orWhere('benefice_total', 'like', "%{$search}%");
                });
            }

            $inventaires = $query->latest()->paginate(10);
            return response()->json($inventaires);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventaireRequest $request)
    {
        try {
            $validated = $request->validated();
            $inventaire = Inventaire::create($validated);
            return response()->json($inventaire, 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la création de l\'inventaire',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Inventaire $inventaire)
    {

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventaireRequest $request, Inventaire $inventaire)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inventaire $inventaire)
    {
        //
    }
}
