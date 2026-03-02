<?php

namespace App\Http\Controllers;

use App\Models\HistoriqueAction;
use App\Http\Requests\StoreHistoriqueActionRequest;
use App\Http\Requests\UpdateHistoriqueActionRequest;
use Illuminate\Http\Request;

class HistoriqueActionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $historiqueActions = HistoriqueAction::with('user', 'produit')
                ->latest();

            if ($request->filled('action')) {
                $historiqueActions->where('action', $request->action);
            }

            if ($request->filled('search')) {
                $search = $request->search;

                $historiqueActions->where(function ($q) use ($search) {
                    $q->whereHas('user', function ($q2) use ($search) {
                        $q2->where('nom', 'like', "%$search%")
                        ->orWhere('prenom', 'like', "%$search%");
                    })
                    ->orWhereHas('produit', function ($q3) use ($search) {
                        $q3->where('nom', 'like', "%$search%");
                    });

                });
            }

            return response()->json($historiqueActions->paginate(10), 200);
        }
        catch (\Throwable $th) {
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
