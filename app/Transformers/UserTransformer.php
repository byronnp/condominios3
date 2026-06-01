<?php

namespace App\Transformers;

use App\Models\Auth\Role;
use App\Models\User;

class UserTransformer
{
    public static function transform(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'identification_type' => $user->identificationType ? [
                'id' => $user->identificationType->id,
                'name' => $user->identificationType->name,
            ] : null,
            'identification_number' => $user->identification_number,
            'mobile_phone' => $user->mobile_phone,
            'landline_phone' => $user->landline_phone,
            'email' => $user->email,
            'role' => $user->userRole ? [
                'id' => $user->userRole->id,
                'name' => $user->userRole->name,
            ] : null,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'last_active_at' => $user->last_active_at,
            'managed_condominiums' => $user->relationLoaded('managedCondominiums')
                ? $user->managedCondominiums->map(fn ($condominium) => [
                    'id' => $condominium->id,
                    'name' => $condominium->name,
                    'role' => self::pivotRole($condominium->pivot->role_id),
                ])->values()
                : null,
        ];
    }

    private static function pivotRole(?int $roleId): ?array
    {
        if (! $roleId) {
            return null;
        }

        $role = Role::query()
            ->with('permissions')
            ->find($roleId);

        return $role ? RoleTransformer::transform($role) : null;
    }
}
