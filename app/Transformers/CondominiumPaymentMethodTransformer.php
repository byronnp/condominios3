<?php

namespace App\Transformers;

use App\Models\Billing\CondominiumPaymentMethod;

class CondominiumPaymentMethodTransformer
{
    public static function transform(CondominiumPaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'condominium_id' => $method->relationLoaded('condominium') && $method->condominium ? [
                'id' => $method->condominium->id,
                'name' => $method->condominium->name,
            ] : null,
            'payment_method' => $method->relationLoaded('paymentMethod') && $method->paymentMethod ? [
                'id' => $method->paymentMethod->id,
                'name' => $method->paymentMethod->name,
            ] : null,
            'display_name' => $method->display_name,
            'is_enabled' => $method->is_enabled,
            'sort_order' => $method->sort_order,
            'instructions' => $method->instructions,
            'has_config' => filled($method->config),
        ];
    }
}
