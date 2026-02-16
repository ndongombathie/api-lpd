<?php

namespace App\Http\Controllers;

use App\Models\fondCaisse;
use App\Http\Requests\StorefondCaisseRequest;
use App\Http\Requests\UpdatefondCaisseRequest;
use App\Models\CaissierCaisseJournal;
use Carbon\Carbon;

class FondCaisseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $fondCaisses = fondCaisse::with('caissier')
            ->orderBy('created_at', 'desc')
            ->get();
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
            $request['date'] = Carbon::today()->format('Y-m-d');
            $fondCaisse = fondCaisse::create($request->validated());
            CaissierCaisseJournal::updateOrCreate(
            ['date' => $request['date']],
            ['fond_ouverture' => $request['montant']]
        );
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
