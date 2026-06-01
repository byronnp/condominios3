<?php

namespace App\Transformers;

use App\Models\Menu\Menu;

class MenuTransformer
{
    public static function transform(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'code' => $menu->code,
            'label' => $menu->label,
            'route_name' => $menu->route_name,
            'path' => $menu->path,
            'icon' => $menu->icon,
            'sort_order' => $menu->sort_order,
            'is_active' => $menu->is_active,
            'required_permission' => $menu->requiredPermission
                ? PermissionTransformer::transform($menu->requiredPermission)
                : null,
            'children' => $menu->relationLoaded('children')
                ? $menu->children->map(fn (Menu $child) => self::transform($child))->values()
                : [],
        ];
    }
}
