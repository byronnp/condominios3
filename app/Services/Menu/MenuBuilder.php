<?php

namespace App\Services\Menu;

use App\Models\Menu\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

class MenuBuilder
{
    /**
     * @return Collection<int, Menu>
     */
    public function forUser(User $user): Collection
    {
        $menus = Menu::query()
            ->with('requiredPermission')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return $this->buildTree($menus, null, $user);
    }

    /**
     * @param  Collection<int, Menu>  $menus
     * @return Collection<int, Menu>
     */
    private function buildTree(Collection $menus, ?int $parentId, User $user): Collection
    {
        return $menus
            ->where('parent_id', $parentId)
            ->map(function (Menu $menu) use ($menus, $user): ?Menu {
                $children = $this->buildTree($menus, $menu->id, $user);
                $isAllowed = $this->isAllowed($menu, $user);

                if (! $isAllowed && $children->isEmpty()) {
                    return null;
                }

                $menu->setRelation('children', $children->values());

                return $menu;
            })
            ->filter()
            ->values();
    }

    private function isAllowed(Menu $menu, User $user): bool
    {
        if ($user->hasPermission('system.manage')) {
            return true;
        }

        $permission = $menu->requiredPermission?->code;

        if (! $permission) {
            return true;
        }

        if ($user->hasPermission($permission)) {
            return true;
        }

        $hasCondominiumPermission = $user->managedCondominiums()
            ->wherePivotNotNull('approved_at')
            ->get()
            ->contains(fn ($condominium) => $user->hasPermission($permission, $condominium->id));

        if ($hasCondominiumPermission) {
            return true;
        }

        return $user->houses()
            ->wherePivotNotNull('approved_at')
            ->get()
            ->contains(fn ($house) => $user->hasHousePermission($permission, $house->id));

    }
}
