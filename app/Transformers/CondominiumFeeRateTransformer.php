<?php

namespace App\Transformers;

use App\Models\Billing\CondominiumFeeRate;

class CondominiumFeeRateTransformer
{
    public static function transform(CondominiumFeeRate $rate): array
    {
        return [
            'id' => $rate->id,
            'condominium_id' => $rate->relationLoaded('condominium') && $rate->condominium ? [
                'id' => $rate->condominium->id,
                'name' => $rate->condominium->name,
            ] : null,
            'amount' => $rate->amount,
            'starts_at' => $rate->starts_at,
            'ends_at' => $rate->ends_at,
            'is_active' => $rate->is_active,
        ];
    }
}
