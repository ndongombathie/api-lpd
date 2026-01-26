<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use App\Http\Requests\StoreDecaissementRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateDecaissementRequest;
use Illuminate\Support\Facades\Auth;

class DecaissementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $decaissements = Decaissement::with(['user', 'caissier'])->paginate(10) ;
            return response()->json($decaissements);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDecaissementRequest $request)
    {
        try {
            $request->merge([
                'user_id' => Auth::user()->id,
            ]);
            $decaissement = Decaissement::create($request->validated());
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
