<?php

namespace App\Transformers;

use App\Models\Billing\Payment;

class PaymentTransformer
{
    public static function transform(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'fee_charge_id' => $payment->fee_charge_id,
            'house_id' => $payment->house_id,
            'registered_by' => $payment->registered_by,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at,
            'payment_method' => $payment->payment_method,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
        ];
    }
}
