<?php

namespace App\Http\Controllers;

use App\Models\EnregistrerVersement;
use App\Http\Requests\StoreEnregistrerVersementRequest;
use App\Http\Requests\UpdateEnregistrerVersementRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EnregistrerVersementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            
            $query = EnregistrerVersement::query()->with('caissier')->orderByDesc('date');

            if ($request->filled('date_debut')) {
                $query->where('date', '>=', $request->date_debut);
            }
            if ($request->filled('date_fin')) {
                $query->where('date', '<=', $request->date_fin);
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('caissier_id', 'like', "%{$search}%")
                      ->orWhere('observation', 'like', "%{$search}%")
                      ->orWhere('montant', 'like', "%{$search}%");
                });
            }

            $enregistrerVersements = $query->paginate(10);

            return response()->json($enregistrerVersements);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEnregistrerVersementRequest $request)
    {
        try {
            $data = $request->validated();
            $data['date'] = $request->date ?? Carbon::today()->toDateString();
            $enregistrerVersement = EnregistrerVersement::create($data);
            return response()->json($enregistrerVersement, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(EnregistrerVersement $enregistrerVersement)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEnregistrerVersementRequest $request, EnregistrerVersement $enregistrerVersement)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EnregistrerVersement $enregistrerVersement)
    {
        //
    }
}
