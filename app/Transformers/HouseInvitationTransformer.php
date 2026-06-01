<?php

namespace App\Transformers;

use App\Models\Auth\Role;
use App\Models\Condominium\HouseInvitation;
use App\Support\ResourceActions;

class HouseInvitationTransformer
{
    public static function transform(HouseInvitation $invitation): array
    {
        $role = $invitation->role_id
            ? Role::query()->with('permissions')->find($invitation->role_id)
            : null;

        return [
            'id' => $invitation->id,
            'house_id' => $invitation->house_id,
            'email' => $invitation->email,
            'role' => $role ? RoleTransformer::transform($role) : null,
            'relationship_type' => $invitation->relationshipType ? [
                'id' => $invitation->relationshipType->id,
                'name' => $invitation->relationshipType->name,
            ] : null,
            'token' => $invitation->token,
            'can_receive_notifications' => $invitation->can_receive_notifications,
            'expires_at' => $invitation->expires_at,
            'accepted_at' => $invitation->accepted_at,
            'revoked_at' => $invitation->revoked_at,
            'actions' => ResourceActions::invitation($invitation),
        ];
    }
}
