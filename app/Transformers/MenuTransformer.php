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
            'required_role' => $menu->required_role,
            'required_permission' => $menu->required_permission,
            'children' => $menu->relationLoaded('children')
                ? $menu->children->map(fn (Menu $child) => self::transform($child))->values()
                : [],
        ];
    }
}
