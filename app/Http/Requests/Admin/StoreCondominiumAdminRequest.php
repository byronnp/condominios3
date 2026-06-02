<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class StoreCondominiumAdminRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->hasPermission('admins.manage')) {
            abort(403, 'Solo el administrador senior puede asignar administradores.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existingUser = User::query()->where('email', $this->input('email'))->first();

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
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'condominium')->where('is_active', true)],
        ];
    }
}
