<?php

namespace App\Transformers;

use App\Models\Auth\Permission;

class PermissionTransformer
{
    public static function transform(Permission $permission): array
    {
        return [
            'id' => $permission->id,
            //'code' => $permission->code,
            'name' => $permission->name,
            'group' => $permission->group,
            'scope' => $permission->scope,
            //'description' => $permission->description,
            //'is_active' => $permission->is_active,
        ];
    }
}
