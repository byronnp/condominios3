<?php

namespace App\Services\Billing;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterPaymentService
{
    public function __construct(
        private readonly PaymentMethodResolver $paymentMethodResolver,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{fee_charge_id:int|string, amount:int|float|string, paid_at?:string|null, condominium_payment_method_id?:int|string|null, reference?:string|null, notes?:string|null}  $data
     */
    public function register(array $data, User $registeredBy, ?Request $request = null): Payment
    {
        $charge = FeeCharge::query()->with('house.condominium')->findOrFail($data['fee_charge_id']);
        $paymentMethod = $this->paymentMethodResolver->resolve($data['condominium_payment_method_id'] ?? null, $charge->house->condominium);

        $payment = DB::transaction(function () use ($data, $paymentMethod, $registeredBy): Payment {
            $charge = FeeCharge::query()->lockForUpdate()->findOrFail($data['fee_charge_id']);

            if ((float) $data['amount'] > (float) $charge->balance) {
                abort(422, 'El pago no puede ser mayor al saldo pendiente.');
            }

            $payment = Payment::query()->create([
                'fee_charge_id' => $charge->id,
                'house_id' => $charge->house_id,
                'registered_by' => $registeredBy->id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? Carbon::now(),
                'payment_method_id' => $paymentMethod?->payment_method_id,
                'condominium_payment_method_id' => $paymentMethod?->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $paidAmount = $charge->payments()->sum('amount');
            $balance = max(0, (float) $charge->amount - (float) $paidAmount);

            $charge->forceFill([
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'paid' : 'partial',
            ])->save();

            return $payment;
        });

        $payment->load(['feeCharge', 'house', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod']);

        $this->audit->record(
            action: 'payment.created',
            module: 'payments',
            condominiumId: $payment->house?->condominium_id,
            user: $registeredBy,
            entity: $payment,
            description: 'Pago registrado para casa '.$payment->house?->code.'.',
            newValues: [
                'amount' => $payment->amount,
                'period' => $payment->feeCharge?->period,
                'house_code' => $payment->house?->code,
            ],
            metadata: [
                'reference' => $payment->reference,
                'payment_method' => $payment->condominiumPaymentMethod?->display_name ?? $payment->paymentMethod?->name,
            ],
            request: $request,
        );

        return $payment;
    }
}
