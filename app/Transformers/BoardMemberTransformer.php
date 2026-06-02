<?php

namespace App\Transformers;

use App\Models\Board\BoardMember;

class BoardMemberTransformer
{
    public static function transform(BoardMember $member): array
    {
        return [
            'id' => $member->id,
            'user' => $member->user ? [
                'id' => $member->user->id,
                'name' => $member->user->name,
                'first_name' => $member->user->first_name,
                'last_name' => $member->user->last_name,
                'email' => $member->user->email,
                'mobile_phone' => $member->user->mobile_phone,
            ] : null,
            'position' => $member->position ? [
                'id' => $member->position->id,
                'name' => $member->position->name,
            ] : null,
            'role' => $member->role ? [
                'id' => $member->role->id,
                'name' => $member->role->name,
            ] : null,
            'starts_at' => $member->starts_at?->toDateString(),
            'ends_at' => $member->ends_at?->toDateString(),
            'is_active' => $member->is_active,
            'notes' => $member->notes,
        ];
    }
}
