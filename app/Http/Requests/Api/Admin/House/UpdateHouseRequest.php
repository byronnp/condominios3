<?php

namespace App\Http\Requests\Api\Admin\House;

use App\Models\Condominium\House;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHouseRequest extends FormRequest
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
        /** @var House $house */
        $house = $this->route('house');

        return [
            'house_number' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $house->condominium_id)->ignore($house),
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
