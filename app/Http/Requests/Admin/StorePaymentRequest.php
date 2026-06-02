<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class StorePaymentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'fee_charge_id' => ['required', 'exists:fee_charges,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'condominium_payment_method_id' => ['nullable', 'exists:condominium_payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
