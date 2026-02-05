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
    public function index()
    {
        try {
            return response()->json(Categorie::query()->latest()->paginate(20));
        } catch (\Throwable $th) {
            //throw $th;
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
    public function update(UpdateCategorieRequest $request, Categorie $categorie)
    {
        try {
            if($request->validated())
            {

                $categorie=Categorie::findOrFail($request->input('id'));
                $categorie->nom=$request->input('nom');
                $categorie->update();
            }
            return response()->json($categorie);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $categorie)
    {
        try {
            $categorie=Categorie::findOrFail($categorie);
            $categorie->delete();
            return response()->json(null,Response::HTTP_NO_CONTENT);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
}
