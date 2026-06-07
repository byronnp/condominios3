<?php

namespace App\Transformers;

use App\Models\User;

class HouseResidentTransformer
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
            'relationship_type' => self::summary($user->getAttribute('relationship_type_id'), $user->getAttribute('relationship_type_name')),
            'role' => self::summary($user->getAttribute('house_role_id'), $user->getAttribute('house_role_name')),
            'is_primary' => (bool) $user->pivot?->is_primary,
            'can_receive_notifications' => (bool) $user->pivot?->can_receive_notifications,
            'approved_at' => $user->pivot?->approved_at,
        ];
    }

    private static function summary(mixed $id, ?string $name): ?array
    {
        if (! $id) {
            return null;
        }

        return [
            'id' => (int) $id,
            'name' => $name,
        ];
    }
}
