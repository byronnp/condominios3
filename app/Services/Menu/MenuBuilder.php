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
        if ($user->isSeniorAdmin()) {
            return true;
        }

        if ($menu->required_role && $menu->required_role !== $user->role) {
            return false;
        }

        if (! $menu->required_permission) {
            return true;
        }

        if ($user->isCondominiumAdmin()) {
            return $user->managedCondominiums()
                ->wherePivot($menu->required_permission, true)
                ->wherePivotNotNull('approved_at')
                ->exists();
        }

        if ($user->role === User::ROLE_RESIDENT) {
            return $user->houses()
                ->wherePivot($menu->required_permission, true)
                ->wherePivotNotNull('approved_at')
                ->exists();
        }

        return false;
    }
}
