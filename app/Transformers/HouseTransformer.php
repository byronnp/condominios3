<?php

namespace App\Transformers;

use App\Models\Condominium\House;
use App\Models\User;
use App\Support\ResourceActions;

class HouseTransformer
{
    public static function transform(House $house): array
    {
        $owner = $house->relationLoaded('ownerUsers')
            ? $house->ownerUsers->sortByDesc(fn ($user) => (bool) $user->pivot->is_primary)->first()
            : null;
        $administrator = $house->relationLoaded('administratorUsers')
            ? $house->administratorUsers->sortByDesc(fn ($user) => (bool) $user->pivot->is_primary)->first()
            : null;

        return [
            'id' => $house->id,
            'condominium_id' => [
                'id' => $house->condominium_id,
                'name' => $house->condominium?->name,
            ],
            'code' => $house->code,
            'house_number' => $house->house_number,
            'address_reference' => $house->address_reference,
            'status' => $house->status,
            'owner' => self::userSummary($owner),
            'administrator' => self::userSummary($administrator),
            'actions' => ResourceActions::house($house),
        ];
    }

    private static function userSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile_phone' => $user->mobile_phone,
            'landline_phone' => $user->landline_phone,
        ];
    }
}
