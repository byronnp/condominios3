<?php

namespace App\Transformers;

use App\Models\Billing\Payment;

class PaymentTransformer
{
    public static function transform(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'payment_batch_id' => $payment->payment_batch_id,
            'fee_charge_id' => $payment->fee_charge_id,
            'house_id' => $payment->house_id,
            'registered_by' => $payment->registered_by,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at,
            'payment_method' => self::paymentMethod($payment),
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'fee_charge' => $payment->relationLoaded('feeCharge') && $payment->feeCharge ? [
                'id' => $payment->feeCharge->id,
                'period' => $payment->feeCharge->period,
                'amount' => $payment->feeCharge->amount,
                'paid_amount' => $payment->feeCharge->paid_amount,
                'balance' => $payment->feeCharge->balance,
                'status' => $payment->feeCharge->status,
                'description' => $payment->feeCharge->description,
            ] : null,
        ];
    }

    private static function paymentMethod(Payment $payment): ?array
    {
        if ($payment->relationLoaded('condominiumPaymentMethod') && $payment->condominiumPaymentMethod) {
            return [
                'id' => $payment->condominiumPaymentMethod->id,
                'display_name' => $payment->condominiumPaymentMethod->display_name,
                'payment_method' => $payment->condominiumPaymentMethod->relationLoaded('paymentMethod') && $payment->condominiumPaymentMethod->paymentMethod ? [
                    'id' => $payment->condominiumPaymentMethod->paymentMethod->id,
                    'name' => $payment->condominiumPaymentMethod->paymentMethod->name,
                ] : null,
            ];
        }

        if ($payment->relationLoaded('paymentMethod') && $payment->paymentMethod) {
            return [
                'id' => $payment->paymentMethod->id,
                'name' => $payment->paymentMethod->name,
            ];
        }

        return null;
    }
}
