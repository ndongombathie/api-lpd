<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use App\Http\Requests\StoreDecaissementRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateDecaissementRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class DecaissementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Decaissement::query()->latest();
            // Filter by motif if provided
            if ($request->filled('motif')) {
                $query->where('motif', $request->input('motif'));
            }

            // Filter by boutique_id if provided
            if ($request->filled('cassier_id')) {
                $query->where('cassier_id', $request->input('cassier_id'));
            }

            // Filter by search term if provided
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('methode_paiement', 'like', "%{$search}%")
                      ->orWhere('date', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
            }

            return response()->json($query->paginate(10));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getDecaissemenentEnAttente(){
        try {
            return response()->json(Decaissement::query()->where('statut', 'en_attente')->with('user')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des décaissements en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function montantTotalDecaissement(){
        try {
            $montantTotal =Decaissement::sum('montant');
            return response()->json(['montant_total' => $montantTotal], 200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDecaissementRequest $request)
    {
        try {
            $data=$request->validated();
            $data['user_id'] = Auth::user()->id;
            $decaissement = Decaissement::create($data);
            return response()->json($decaissement, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Decaissement $decaissement)
    {
        try {
            return response()->json(Decaissement::findOrFail($decaissement->id));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateStatusDecaissement(Request $request, Decaissement $decaissement)
    {
        try {
            $decaissement->update(['statut' => $request->statut]);
            $decaissement->update(['caissier_id' => Auth::user()->id]);
            return response()->json($decaissement);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDecaissementRequest $request, Decaissement $decaissement)
    {
        try {
            $decaissement->update($request->validated());
            return response()->json($decaissement);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Decaissement $decaissement)
    {
        try {
            $decaissement->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


}
