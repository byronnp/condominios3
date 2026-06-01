<?php

namespace App\Transformers;

use App\Models\Auth\Role;
use App\Models\User;

class CondominiumAdminTransformer
{
    public static function transform(User $user): array
    {
        $role = $user->pivot?->role_id
            ? Role::query()->with('permissions')->find($user->pivot->role_id)
            : null;

        return [
            ...UserTransformer::transform($user),
            'condominium_role' => $role ? RoleTransformer::transform($role) : null,
        ];
    }
}
