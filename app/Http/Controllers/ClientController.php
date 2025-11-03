<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return Client::query()->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string',
            'prenom' => 'required|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type_client' => 'required|in:normal,special',
            'solde' => 'nullable|numeric',
            'contact' => 'nullable|string',
        ]);
        $client = Client::create($data);
        return response()->json($client, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Client::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Client::findOrFail($id);
        $data = $request->validate([
            'nom' => 'sometimes|string',
            'prenom' => 'sometimes|string',
            'adresse' => 'nullable|string',
            'numero_cni' => 'nullable|string',
            'telephone' => 'nullable|string',
            'type_client' => 'sometimes|in:normal,special',
            'solde' => 'nullable|numeric',
            'contact' => 'nullable|string',
        ]);
        $client->update($data);
        return $client;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);
        $client->delete();
        return response()->noContent();
    }
}
