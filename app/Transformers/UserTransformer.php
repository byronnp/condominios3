<?php

namespace App\Transformers;

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
            'role' => $user->role,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at,
            'last_active_at' => $user->last_active_at,
            'managed_condominiums' => $user->relationLoaded('managedCondominiums')
                ? $user->managedCondominiums->map(fn ($condominium) => [
                    'id' => $condominium->id,
                    'name' => $condominium->name,
                    'permissions' => [
                        'can_manage_houses' => (bool) $condominium->pivot->can_manage_houses,
                        'can_manage_residents' => (bool) $condominium->pivot->can_manage_residents,
                        'can_manage_fees' => (bool) $condominium->pivot->can_manage_fees,
                        'can_manage_payments' => (bool) $condominium->pivot->can_manage_payments,
                        'can_manage_invitations' => (bool) $condominium->pivot->can_manage_invitations,
                    ],
                ])->values()
                : null,
        ];
    }
}
