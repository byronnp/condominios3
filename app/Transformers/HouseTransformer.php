<?php

namespace App\Transformers;

use App\Models\Condominium\House;

class HouseTransformer
{
    public static function transform(House $house): array
    {
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
        ];
    }
}
