<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventaireRequest extends FormRequest
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
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'date' => 'nullable|date',
            'prix_achat_total' => 'nullable|integer',
            'prix_valeur_sortie_total' => 'nullable|integer',
            'valeur_estimee_total' => 'nullable|integer',
            'benefice_total' => 'nullable|integer',
        ];
    }
}
