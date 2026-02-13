<?php

namespace App\Http\Controllers;

use App\Models\fondCaisse;
use App\Http\Requests\StorefondCaisseRequest;
use App\Http\Requests\UpdatefondCaisseRequest;

class FondCaisseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $fondCaisses = fondCaisse::with('caissier')->get();
            return response()->json($fondCaisses, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorefondCaisseRequest $request)
    {
        try {
            $request['date'] = now()->format('Y-m-d');
            $fondCaisse = fondCaisse::create($request->validated());
            return response()->json($fondCaisse, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(fondCaisse $fondCaisse)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatefondCaisseRequest $request, fondCaisse $fondCaisse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(fondCaisse $fondCaisse)
    {
        //
    }
}
