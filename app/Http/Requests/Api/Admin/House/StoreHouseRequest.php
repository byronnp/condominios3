<?php

namespace App\Http\Requests\Api\Admin\House;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'condominium_id' => ['required', 'exists:condominiums,id'],
            'house_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $this->input('condominium_id')),
            ],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'house_number.unique' => 'Ya existe una casa con este numero en el condominio.',
        ];
    }
}
