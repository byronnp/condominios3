<?php

namespace App\Transformers;

use App\Models\Condominium\HouseInvitation;

class HouseInvitationTransformer
{
    public static function transform(HouseInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'house_id' => $invitation->house_id,
            'email' => $invitation->email,
            'relationship' => $invitation->relationship,
            'token' => $invitation->token,
            'can_view_balance' => $invitation->can_view_balance,
            'can_view_payments' => $invitation->can_view_payments,
            'can_make_payments' => $invitation->can_make_payments,
            'can_receive_notifications' => $invitation->can_receive_notifications,
            'can_invite_users' => $invitation->can_invite_users,
            'expires_at' => $invitation->expires_at,
            'accepted_at' => $invitation->accepted_at,
            'revoked_at' => $invitation->revoked_at,
        ];
    }
}
