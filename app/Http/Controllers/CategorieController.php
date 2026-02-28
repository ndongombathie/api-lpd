<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Http\Requests\StoreCategorieRequest;
use App\Http\Requests\UpdateCategorieRequest;
use Illuminate\Http\Response;

class CategorieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Response $request)
    {
        try {
            $query= Categorie::query()
            ->latest();

            /* if($request->filled('search'))
            {
                $search = $request->input('search');
                $query->where('nom', 'like', "%{$search}%");
            } */

            return response()->json($query->paginate(10));
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    public function nombreCategorie()
    {
        try {
            return response()->json(Categorie::count());
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategorieRequest $request)
    {
         try {
             $categorie=Categorie::create($request->validated());
             return response()->json($categorie);
         } catch (\Throwable $th) {
            //throw $th;
         }
    }

    /**
     * Display the specified resource.
     */
    public function show(String $categorie)
    {
        try {
            $categorie=Categorie::findOrFail($categorie);
            return response()->json($categorie);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategorieRequest $request, string $categorie)
    {
        try {
            if($request->validated())
            {

                $categorie=Categorie::findOrFail($categorie);
                $categorie->nom=$request->input('nom');
                $categorie->update();
            }
            return response()->json($categorie);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
            //throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $categorie)
    {
        try {
            $categorie = Categorie::findOrFail($categorie);

            // Détacher les produits associés avant suppression pour éviter
            // la suppression en cascade des produits qui échouerait s'ils ont des ventes
            $categorie->produits()->update(['categorie_id' => null]);

            $categorie->delete();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
