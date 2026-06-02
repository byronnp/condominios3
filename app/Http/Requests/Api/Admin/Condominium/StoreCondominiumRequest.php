<?php

namespace App\Http\Requests\Api\Admin\Condominium;

use App\Models\Catalog\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCondominiumRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'ruc' => ['nullable', 'string', 'max:20', 'unique:condominiums,ruc'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'status_id' => ['sometimes', Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                ->where('catalog_id', $this->condominiumStatusCatalogId())
                ->where('is_active', true))],
            'total_houses' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function condominiumStatusCatalogId(): int
    {
        return (int) Catalog::query()->where('code', 'condominium_statuses')->value('id');
    }
}
