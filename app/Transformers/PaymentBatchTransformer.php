<?php

namespace App\Transformers;

use App\Models\Billing\PaymentBatch;
use App\Support\ResourceActions;

class PaymentBatchTransformer
{
    public static function transform(PaymentBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'house_id' => $batch->house_id,
            'registered_by' => $batch->registered_by,
            'total_amount' => $batch->total_amount,
            'payment_method' => self::paymentMethod($batch),
            'reference' => $batch->reference,
            'paid_at' => $batch->paid_at,
            'notes' => $batch->notes,
            'payments' => $batch->relationLoaded('payments')
                ? $batch->payments->map(fn ($payment) => PaymentTransformer::transform($payment))->values()
                : null,
            'actions' => ResourceActions::paymentBatch($batch),
        ];
    }

    private static function paymentMethod(PaymentBatch $batch): ?array
    {
        if ($batch->relationLoaded('condominiumPaymentMethod') && $batch->condominiumPaymentMethod) {
            return [
                'id' => $batch->condominiumPaymentMethod->id,
                'display_name' => $batch->condominiumPaymentMethod->display_name,
                'payment_method' => $batch->condominiumPaymentMethod->relationLoaded('paymentMethod') && $batch->condominiumPaymentMethod->paymentMethod ? [
                    'id' => $batch->condominiumPaymentMethod->paymentMethod->id,
                    'name' => $batch->condominiumPaymentMethod->paymentMethod->name,
                ] : null,
            ];
        }

        if ($batch->relationLoaded('paymentMethod') && $batch->paymentMethod) {
            return [
                'id' => $batch->paymentMethod->id,
                'name' => $batch->paymentMethod->name,
            ];
        }

        return null;
    }
}
