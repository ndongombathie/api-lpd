<?php

namespace App\Http\Controllers;

use App\Models\Decaissement;
use App\Http\Requests\StoreDecaissementRequest;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateDecaissementRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DecaissementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {


            $query = Decaissement::query()->with(['user', 'caissier'])
                ->where('caissier_id', Auth::user()->id)
                ->latest('updated_at');
            // Filter by role if provided
            if ($request->filled('motif')) {
                $query->where('motif', $request->input('motif'));
            }

            // Filter by boutique_id if provided
            if ($request->filled('cassier_id')) {
                $query->where('cassier_id', $request->input('cassier_id'));
            }

            // Filter by search term if provided
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('methode_paiement', 'like', "%{$search}%")
                      ->orWhere('date', 'like', "%{$search}%")
                      ->orWhere('statut', 'like', "%{$search}%");
                });
            }

            $perPage = (int) $request->input('per_page', 15);
            $perPage = min(max($perPage, 1), 200);
            return response()->json($query->paginate($perPage));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getDecaissemenentEnAttente(){
        try {
            return response()->json(Decaissement::query()->where('statut', 'en_attente')->with('user')->latest()->paginate(20));
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des décaissements en attente',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function montantTotalDecaissement(){
        try {
            $montantTotal =Decaissement::where('caissier_id', Auth::user()->id)->sum('montant');
            return response()->json(['montant_total' => $montantTotal], 200);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }


    /**
     * Récupère les décaissements en attente
     */
    public function getDecaissementsEnAttente()
    {
        try {
            // Le statut peut être stocké avec une majuscule, donc on utilise whereRaw ou LOWER
            $decaissements = Decaissement::with(['user', 'caissier'])
                ->whereRaw('LOWER(statut) = ?', ['en_attente'])
                ->orderBy('created_at', 'asc')
                ->get();

            // Retourner les valeurs brutes directement depuis la base de données
            $decaissementsArray = $decaissements->map(function ($dec) {
                // Utiliser getAttributes() pour obtenir les valeurs brutes
                $attributes = $dec->getAttributes();

                return [
                    'id' => $attributes['id'] ?? $dec->id,
                    'user_id' => $attributes['user_id'] ?? $dec->user_id,
                    'caissier_id' => $attributes['caissier_id'] ?? $dec->caissier_id,
                    'motif' => $attributes['motif'] ?? $dec->motif ?? 'Non spécifié',
                    'libelle' => $attributes['libelle'] ?? $dec->libelle ?? 'Non spécifié',
                    'montant' => (int)($attributes['montant'] ?? $dec->montant ?? 0),
                    'methode_paiement' => $attributes['methode_paiement'] ?? $dec->methode_paiement ?? 'caisse',
                    'date' => $attributes['date'] ?? ($dec->date ? $dec->date->format('Y-m-d') : null),
                    'statut' => strtolower($attributes['statut'] ?? $dec->statut ?? 'en_attente'),
                    'created_at' => $dec->created_at ? $dec->created_at->toISOString() : null,
                    'updated_at' => $dec->updated_at ? $dec->updated_at->toISOString() : null,
                    'user' => $dec->user ? [
                        'id' => $dec->user->id,
                        'nom' => $dec->user->nom,
                        'prenom' => $dec->user->prenom,
                        'email' => $dec->user->email,
                    ] : null,
                    'caissier' => $dec->caissier ? [
                        'id' => $dec->caissier->id,
                        'nom' => $dec->caissier->nom,
                        'prenom' => $dec->caissier->prenom,
                        'email' => $dec->caissier->email,
                    ] : null,
                ];
            });

            return response()->json(['data' => $decaissementsArray->values()]);
        } catch (\Exception $e) {
            Log::error('Erreur getDecaissementsEnAttente: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDecaissementRequest $request)
    {
        try {
            $data=$request->validated();
            $data['user_id'] = Auth::user()->id;
            $decaissement = Decaissement::create($data);
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
            $payload = [
                'statut' => $request->statut,
                'caissier_id' => Auth::user()->id,
                // Utiliser l'heure exacte actuelle pour la validation
                'date' => now(),
            ];

            // Optionnel: permettre au caissier de choisir le compte/méthode utilisée
            if ($request->filled('methode_paiement')) {
                $payload['methode_paiement'] = $request->methode_paiement;
            }

            // Utiliser l'heure exacte actuelle pour la validation
            $decaissement->update($payload);
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
