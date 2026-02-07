<?php

namespace App\Repositories;

use JasonGuru\LaravelMakeRepository\Repository\BaseRepository;
use App\Models\Produit;
use Illuminate\Pagination\Paginator;

//use Your Model

/**
 * Class ProduitRepository.
 */
class ProduitRepository extends BaseRepository
{
    /**
     * @return string
     *  Return the model
     */
    public function model(): string
    {
        return Produit::class;
    }

    /**
     * @return array
     *  Return the model with the specified attributes
     */
    public function attributes()
    {
        return [
            'id',
            'nom',
            'prix',
            'description',
            'stock',
            'created_at',
            'updated_at',
        ];
    }

    function index() {
        $produits = Produit::query()->with(['categorie','entreees_sorties'])->latest()->paginate(20);
        $produits->each(function($produit) {
            $produit->etat_stock = $produit->nombre_carton < $produit->stock_seuil ? true : false;
            $produit->etat_rupture= $produit->nombre_carton <= 0 ? true : false;
        });
        return $produits;
    }
}
