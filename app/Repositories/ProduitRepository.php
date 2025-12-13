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
        return Produit::query()->latest()->paginate(20);
    }
}
