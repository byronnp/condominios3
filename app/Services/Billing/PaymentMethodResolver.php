<?php

namespace App\Services\Billing;

use App\Models\Billing\CondominiumPaymentMethod;
use App\Models\Condominium\Condominium;

class PaymentMethodResolver
{
    public function resolve(?int $condominiumPaymentMethodId, Condominium $condominium): ?CondominiumPaymentMethod
    {
        if (! $condominiumPaymentMethodId) {
            return null;
        }

        $paymentMethod = CondominiumPaymentMethod::query()
            ->with('paymentMethod')
            ->whereKey($condominiumPaymentMethodId)
            ->where('condominium_id', $condominium->id)
            ->where('is_enabled', true)
            ->whereHas('paymentMethod', function ($query): void {
                $query->where('is_active', true)
                    ->whereHas('catalog', fn ($catalogQuery) => $catalogQuery->where('code', 'payment_methods'));
            })
            ->first();

        if (! $paymentMethod) {
            abort(422, 'El metodo de pago no esta habilitado para este condominio.');
        }

        return $paymentMethod;
    }
}
