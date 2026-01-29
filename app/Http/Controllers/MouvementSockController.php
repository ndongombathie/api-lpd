<?php

namespace App\Http\Controllers;

use App\Models\EntreeSortie;
use App\Models\MouvementStock;
use Illuminate\Http\Request;

class MouvementSockController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $mouvements = MouvementStock::query()->with('produit')->paginate(10);
            $mouvements->getCollection()->transform(function ($mouvement) {
                $mouvement->entree_sortie = EntreeSortie::where('produit_id', $mouvement->produit_id)->first();
                return $mouvement;
            });
            return response()->json($mouvements);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
