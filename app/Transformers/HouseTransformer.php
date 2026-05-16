<?php

namespace App\Transformers;

use App\Models\Condominium\House;

class HouseTransformer
{
    public static function transform(House $house): array
    {
        return [
            'id' => $house->id,
            'condominium_id' => $house->condominium_id,
            'code' => $house->code,
            'house_number' => $house->house_number,
            'address_reference' => $house->address_reference,
            'status' => $house->status,
            'condominium' => $house->relationLoaded('condominium') && $house->condominium
                ? CondominiumTransformer::transform($house->condominium)
                : null,
        ];
    }
}
