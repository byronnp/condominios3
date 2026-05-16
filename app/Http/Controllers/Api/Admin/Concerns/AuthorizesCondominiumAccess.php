<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait AuthorizesCondominiumAccess
{
    protected function canManageCondominium(User $user, int $condominiumId, string $permission): bool
    {
        if ($user->isSeniorAdmin()) {
            return true;
        }

        return $user->managedCondominiums()
            ->where('condominiums.id', $condominiumId)
            ->wherePivot($permission, true)
            ->wherePivotNotNull('approved_at')
            ->exists();
    }

    protected function abortUnlessCanManageCondominium(User $user, int $condominiumId, string $permission): void
    {
        if (! $this->canManageCondominium($user, $condominiumId, $permission)) {
            abort(403, 'No autorizado para administrar este condominio.');
        }
    }

    protected function scopeCondominiumsFor(User $user, Builder $query): Builder
    {
        if ($user->isSeniorAdmin()) {
            return $query;
        }

        return $query->whereIn('condominiums.id', $this->managedCondominiumIds($user));
    }

    /**
     * @return list<int>
     */
    protected function managedCondominiumIds(User $user): array
    {
        return $user->managedCondominiums()
            ->wherePivotNotNull('approved_at')
            ->pluck('condominiums.id')
            ->all();
    }
}
