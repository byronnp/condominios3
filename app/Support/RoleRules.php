<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class RoleRules
{
    public static function activeInScopeForCondominium(string $scope, ?int $condominiumId): Exists
    {
        return Rule::exists('roles', 'id')
            ->where('scope', $scope)
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('condominium_id')
                ->when($condominiumId, fn ($query) => $query->orWhere('condominium_id', $condominiumId)));
    }
}
