<?php

namespace App\Transformers;

use App\Models\Billing\FeeCharge;

class FeeChargeTransformer
{
    public static function transform(FeeCharge $charge): array
    {
        return [
            'id' => $charge->id,
            'house_id' => $charge->house_id,
            'period' => $charge->period,
            'amount' => $charge->amount,
            'paid_amount' => $charge->paid_amount,
            'balance' => $charge->balance,
            'due_date' => $charge->due_date,
            'status' => $charge->status,
            'description' => $charge->description,
        ];
    }
}
