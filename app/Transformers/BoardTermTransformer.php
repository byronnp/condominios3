<?php

namespace App\Transformers;

use App\Models\Board\BoardTerm;

class BoardTermTransformer
{
    public static function transform(BoardTerm $term): array
    {
        return [
            'id' => $term->id,
            'condominium' => $term->condominium ? [
                'id' => $term->condominium->id,
                'name' => $term->condominium->name,
            ] : null,
            'name' => $term->name,
            'starts_at' => $term->starts_at?->toDateString(),
            'ends_at' => $term->ends_at?->toDateString(),
            'status' => $term->status,
            'notes' => $term->notes,
            'members' => $term->relationLoaded('members')
                ? $term->members->map(fn ($member) => BoardMemberTransformer::transform($member))->values()
                : null,
        ];
    }
}
