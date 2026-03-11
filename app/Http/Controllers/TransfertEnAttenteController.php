<?php

namespace App\Http\Controllers;
use App\Models\EntreeSortieBoutique;
use App\Models\EntreeSortie;
use Illuminate\Http\Request;
use App\Models\Produit;
use App\Models\TransfertEnAttente;
use App\Models\Transfer;
use App\Http\Requests\StoreTransfertEnAttenteRequest;
use App\Http\Requests\UpdateTransfertEnAttenteRequest;
use App\Events\TransfertValidee;
use Illuminate\Support\Facades\Auth;

class TransfertEnAttenteController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request)
    {
        try {
            $transfers = TransfertEnAttente::with(['produit'])
            ->where('status', 'en_attente')
            ->latest('created_at');

            if($request->filled('search')){
                $search = $request->input('search');
                $transfers->where(function ($q) use ($search) {
                    $q->whereHas('produit', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhere('quantite', 'like', "%{$search}%")
                    ->orWhere('seuil', 'like', "%{$search}%")
                    ->orWhere('nombre_carton', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%");
                });
            }
            return response()->json($transfers->paginate(10));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function alltransfert(Request $request){
      try {
        $transfers = TransfertEnAttente::with(['produit'])
        ->latest();

        # Filter by search term if provided
        if ($request->filled('search')) {
          $search = $request->input('search');
          $transfers->where(function ($q) use ($search) {
              $q->whereHas('produit', function ($sub) use ($search) {
                  $sub->where('nom', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
              })
            ->orWhere('status', 'like', "%{$search}%")
            ->orWhere('quantite', 'like', "%{$search}%")
            ->orWhere('seuil', 'like', "%{$search}%")
            ->orWhere('nombre_carton', 'like', "%{$search}%")
              ->orWhere('created_at', 'like', "%{$search}%");
          });
        }

        return response()->json($transfers->paginate(10));
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


    /**
     * Get the total count of products in transfers.
     */
    public function nombreProduits()
    {
        try {
            $count = TransfertEnAttente::where('status', 'valide')->count();
            return response()->json(['total' => $count]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    /**
     * Get the total quantity of products in transfers.
     */
    public function quantiteTotaleProduit()
    {
        try {
                $totalQuantity = TransfertEnAttente::where('status', 'valide')->sum('quantite');
                return response()->json(['total_quantity' => $totalQuantity]);
        } catch (\Throwable $th) {
                return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsDisponibles(Request $request)
    {
        try {

            $transfers = TransfertEnAttente::with(['produit.categorie'])->where('status', 'valide')
            ->where('quantite','>',0)
            ->latest('updated_at');

            if($request->filled('search')){
                $search = $request->input('search');
                $transfers->where(function ($q) use ($search) {
                    $q->whereHas('produit', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhere('quantite', 'like', "%{$search}%")
                    ->orWhere('seuil', 'like', "%{$search}%")
                    ->orWhere('nombre_carton', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%");
                });
            }

            return response()->json($transfers->paginate(12));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsControleBoutique(Request $request)
    {
        try {
            $transfers = TransfertEnAttente::with(['produit.categorie'])
            ->where('status', 'valide')
            ->latest();
            if($request->filled('search')){
                $search = $request->input('search');
                $transfers->where(function ($q) use ($search) {
                    $q->whereHas('produit', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhere('quantite', 'like', "%{$search}%")
                    ->orWhere('seuil', 'like', "%{$search}%")
                    ->orWhere('nombre_carton', 'like', "%{$search}%")
                    ->orWhere('created_at', 'like', "%{$search}%");
                });
            }
            $transfers->each(function($transfer) {
                $transfer->produit->etat_stock = $transfer->quantite < $transfer->seuil ? true : false;
                $transfer->produit->entree_sortie = EntreeSortieBoutique::where('produit_id', $transfer->produit_id)->get()->first();
            });
            return response()->json($transfers->paginate(10));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    #get un product buy code in transfert en attente
    public function getProductByCode($code)
    {
        try {
                $transfer = TransfertEnAttente::with('produit.categorie')
                ->where('status', 'valide')
                ->where('quantite','>',0)
                ->whereHas('produit', function($q) use ($code) {
                    $q->where('code', $code);
                })->get()->first();
                return response()->json($transfer);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsControleDepots(Request $request)
    {
        try {
            $produits = Produit::with(['entreees_sorties', 'fournisseur'])
                        ->latest('created_at');
            // Filtrer par nom ou code
            if ($request->filled('search')) {
                $search = $request->input('search');
                $produits->where(function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
                });
            }
            // Récupérer les produits
            $produits = $produits->get();
            // Ajouter l'état du stock
            $produits->each(function($produit) {
                $produit->etat_stock = $produit->quantite < $produit->stock_seuil;
            });
            return response()->json($produits);

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function getTransferValide(Request $request){
      try {
        $transfers = TransfertEnAttente::with(['produit'])->where('status', 'valide')->latest('updated_at');

        if($request->filled('search')){
          $search = $request->input('search');
          $transfers->where(function ($q) use ($search) {
              $q->whereHas('produit', function ($sub) use ($search) {
                  $sub->where('nom', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
              })->orWhere('created_at', 'like', "%{$search}%");
          });
        }
        return response()->json($transfers->paginate(10));
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


    /**
     * Get products below stock threshold
     */
    public function produitsSousSeuil(Request $request)
    {
        try {
            $transfers = TransfertEnAttente::with(['produit'])
                ->where('status', 'valide')
                ->whereRaw('quantite <= seuil')
                ->latest();

            if($request->filled('search')){
                $search = $request->input('search');
                $transfers->where(function ($q) use ($search) {
                    $q->whereHas('produit', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })->orWhere('created_at', 'like', "%{$search}%");
                });
            }
            return response()->json($transfers->paginate(10));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function produitsRupture(Request $request)
    {
        try {
            $transfers = TransfertEnAttente::with(['produit'])
                ->where('status', 'valide')
                ->whereRaw('quantite <= 0')
                ->latest();

            if($request->filled('search')){
                $search = $request->input('search');
                $transfers->where(function ($q) use ($search) {
                    $q->whereHas('produit', function ($sub) use ($search) {
                        $sub->where('nom', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })->orWhere('created_at', 'like', "%{$search}%");
                });
            }
            return response()->json($transfers->paginate(10));
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }


    public function valideTransfer(Request $request){
      try {
            $transfer = TransfertEnAttente::findOrFail($request->id);
            $transfer->status = 'valide';
            $transfer->seuil = $request->seuil;
            $transfer->prix_vente_detail = $request->prix_vente_detail;
            $transfer->prix_vente_gros = $request->prix_vente_gros;
            $transfer->prix_seuil_detail = $request->prix_seuil_detail;
            $transfer->prix_seuil_gros = $request->prix_seuil_gros;
            $transfer->save();
           event(new TransfertValidee($transfer, Auth::user()->boutique_id));
            return response()->json($transfer);
      } catch (\Throwable $th) {
        return response()->json(['error' => $th->getMessage()], 500);
      }
    }


     public function MontantTotalStock()
    {
        try {
           $total = 0;
           foreach (TransfertEnAttente::where('status', 'valide')->get() as $transfer) {
            $total += $transfer->quantite * $transfer->prix_vente_detail;
           }
           return response()->json(['total' => $total]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    #nombre de transferts en attente
    public function nombreTransferEnAttente(){
        try {
            return response()->json(TransfertEnAttente::where('status','en_attente')->count());
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }
    #nombre de transfert annuler
    public function nombreTransfertAnnuler(){
        try {
            return response()->json(TransfertEnAttente::where('status','annuler')->count());
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    #liste des transfert annulers
    public function transfertAnnuler(){
        try {
            return response()->json(TransfertEnAttente::with('produit')->where('status', 'annuler')->paginate(10));
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }




    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTransfertEnAttenteRequest $request, TransfertEnAttente $TransfertEnAttente)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TransfertEnAttente $TransfertEnAttente)
    {
        //
    }
}
