<?php

namespace App\Http\Requests\Api\Admin\Board;

use App\Models\Auth\Permission;
use App\Models\Catalog\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoardMemberRequest extends FormRequest
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
            'position_id' => ['sometimes', Rule::exists('catalog_items', 'id')->where('catalog_id', $this->boardPositionsCatalogId())->where('is_active', true)],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('scope', Permission::SCOPE_CONDOMINIUM)->where('is_active', true)],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function boardPositionsCatalogId(): int
    {
        return (int) Catalog::query()->where('code', 'board_positions')->value('id');
    }
}
