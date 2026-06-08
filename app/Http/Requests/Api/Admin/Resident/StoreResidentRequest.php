<?php

namespace App\Http\Requests\Api\Admin\Resident;

use App\Models\Auth\Permission;
use App\Models\Condominium\House;
use App\Models\User;
use App\Support\RoleRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentRequest extends FormRequest
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
        $existingUser = User::query()->where('email', $this->input('email'))->first();
        $condominiumId = $this->condominiumId();

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'identification_type_id' => ['required', 'exists:catalog_items,id'],
            'identification_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('users', 'identification_number')->ignore($existingUser?->id),
            ],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'landline_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'house_id' => ['required', 'exists:houses,id'],
            'relationship_type_id' => ['required', 'exists:catalog_items,id'],
            'role_id' => ['sometimes', RoleRules::activeInScopeForCondominium(Permission::SCOPE_RESIDENT, $condominiumId)],
            'is_primary' => ['sometimes', 'boolean'],
            'can_receive_notifications' => ['sometimes', 'boolean'],
        ];
    }

    private function condominiumId(): ?int
    {
        if (! $this->filled('house_id')) {
            return null;
        }

        $condominiumId = House::query()->whereKey($this->input('house_id'))->value('condominium_id');

        return $condominiumId ? (int) $condominiumId : null;
    }
}
