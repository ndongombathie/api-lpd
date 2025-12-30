<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDecaissementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motif' => 'required|string|max:255',
            'libelle'=> 'required|string|max:255',
            'date' => 'required|date',
            'statut' => 'required|string|max:255',
            'montant' => 'required|numeric|min:0',
            'methode_paiement' => 'required|string|max:255',
        ];
    }
}
