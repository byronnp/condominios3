<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateCondominiumAdminRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->hasPermission('admins.manage')) {
            abort(403, 'Solo el administrador senior puede editar administradores de condominio.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $admin */
        $admin = $this->route('admin');

        return [
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'identification_type_id' => ['sometimes', 'exists:catalog_items,id'],
            'identification_number' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('users', 'identification_number')->ignore($admin?->id),
            ],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'landline_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin?->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'condominium')->where('is_active', true)],
        ];
    }
}
