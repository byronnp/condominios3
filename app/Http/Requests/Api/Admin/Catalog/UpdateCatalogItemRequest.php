<?php

namespace App\Http\Requests\Api\Admin\Catalog;

use App\Models\Catalog\CatalogItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogItemRequest extends FormRequest
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
        /** @var CatalogItem $catalogItem */
        $catalogItem = $this->route('catalogItem');

        return [
            'code' => ['sometimes', 'string', 'max:80', Rule::unique('catalog_items', 'code')->where('catalog_id', $catalogItem->catalog_id)->ignore($catalogItem)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
