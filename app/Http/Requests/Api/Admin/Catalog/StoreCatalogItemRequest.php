<?php

namespace App\Http\Requests\Api\Admin\Catalog;

use App\Models\Catalog\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCatalogItemRequest extends FormRequest
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
        /** @var Catalog $catalog */
        $catalog = $this->route('catalog');

        return [
            'code' => ['required', 'string', 'max:80', Rule::unique('catalog_items', 'code')->where('catalog_id', $catalog->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
