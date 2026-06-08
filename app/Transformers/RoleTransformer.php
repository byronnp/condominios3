<?php

namespace App\Transformers;

use App\Models\Auth\Role;

class RoleTransformer
{
    public static function transform(Role $role): array
    {
        return [
            'id' => $role->id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'scope' => $role->scope,
            'condominium' => $role->condominium_id
                ? [
                    'id' => $role->condominium_id,
                    'name' => $role->relationLoaded('condominium') ? $role->condominium?->name : null,
                ]
                : null,
            'is_system' => $role->is_system,
            'is_active' => $role->is_active,
            'permissions' => $role->relationLoaded('permissions')
                ? $role->permissions->map(fn ($permission) => PermissionTransformer::transform($permission))->values()
                : null,
        ];
    }
}
