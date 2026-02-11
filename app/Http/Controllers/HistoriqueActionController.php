<?php

namespace App\Http\Controllers;

use App\Models\HistoriqueAction;
use App\Http\Requests\StoreHistoriqueActionRequest;
use App\Http\Requests\UpdateHistoriqueActionRequest;

class HistoriqueActionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            return HistoriqueAction::with('user','produit')->paginate(50);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHistoriqueActionRequest $request)
    {
        try {
            $data = $request->validated();
            $historiqueAction = HistoriqueAction::create($data);
            return response()->json($historiqueAction, 201);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(HistoriqueAction $historiqueAction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHistoriqueActionRequest $request, HistoriqueAction $historiqueAction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HistoriqueAction $historiqueAction)
    {
        try {
            $historiqueAction->delete();
            return response()->json(null, 204);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
