<?php

namespace App\Transformers;

use App\Models\Condominium\Condominium;

class CondominiumTransformer
{
    public static function transform(Condominium $condominium): array
    {
        return [
            'id' => $condominium->id,
            'name' => $condominium->name,
            'address' => $condominium->address,
            'is_active' => $condominium->is_active,
            'houses_count' => $condominium->houses_count ?? null,
            'houses' => $condominium->relationLoaded('houses')
                ? $condominium->houses->map(fn ($house) => [
                    'id' => $house->id,
                    'code' => $house->code,
                    'house_number' => $house->house_number,
                    'address_reference' => $house->address_reference,
                    'status' => $house->status,
                ])->values()
                : null,
        ];
    }
}
