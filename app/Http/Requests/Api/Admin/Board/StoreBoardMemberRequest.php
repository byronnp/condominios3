<?php

namespace App\Http\Requests\Api\Admin\Board;

use App\Models\Auth\Permission;
use App\Models\Catalog\Catalog;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBoardMemberRequest extends FormRequest
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
            'user_id' => ['nullable', 'required_without:email', 'exists:users,id'],
            'first_name' => ['required_without:user_id', 'string', 'max:120'],
            'last_name' => ['required_without:user_id', 'string', 'max:120'],
            'identification_type_id' => ['nullable', 'exists:catalog_items,id'],
            'identification_number' => ['nullable', 'string', 'max:40', Rule::unique('users', 'identification_number')->ignore($this->existingUserId())],
            'mobile_phone' => ['nullable', 'string', 'max:40'],
            'landline_phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'required_without:user_id', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'position_id' => ['required', Rule::exists('catalog_items', 'id')->where('catalog_id', $this->boardPositionsCatalogId())->where('is_active', true)],
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('scope', Permission::SCOPE_CONDOMINIUM)->where('is_active', true)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function boardPositionsCatalogId(): int
    {
        return (int) Catalog::query()->where('code', 'board_positions')->value('id');
    }

    private function existingUserId(): ?int
    {
        if ($this->filled('user_id')) {
            return $this->integer('user_id');
        }

        if (! $this->filled('email')) {
            return null;
        }

        return User::query()->where('email', $this->input('email'))->value('id');
    }
}
