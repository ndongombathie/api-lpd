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
    public function store(StorefondCaisseRequest $request,string $id)
    {
        try {
            $data=$request->validated();
            $data['date'] = Carbon::today()->format('Y-m-d');
            $data['caissier_id']=$id;
            #update si la date et caissier_id existe sinon creer
            $fondCaisse = fondCaisse::updateOrCreate(
                [
                    'date' => $data['date'],
                    'caissier_id' => $id,
                ],
                $data
            );

            #update si la date et caissier_id existe sinon cree
            CaissierCaisseJournal::updateOrCreate(
                [
                    'date' => $data['date'],
                    'caissier_id' => $id,
                ],
                [
                    'fond_ouverture' => $data['montant'],
                ]
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
