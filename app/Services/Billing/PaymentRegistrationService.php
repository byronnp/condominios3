<?php

namespace App\Services\Billing;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentBatch;
use App\Models\Condominium\House;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentRegistrationService
{
    public function __construct(
        private readonly MonthlyFeeChargeGenerator $monthlyFeeChargeGenerator,
        private readonly PaymentMethodResolver $paymentMethodResolver,
    ) {}

    /**
     * @param  array{
     *     fee_charge_id:int,
     *     amount:int|float|string,
     *     paid_at?:string|null,
     *     condominium_payment_method_id?:int|null,
     *     reference?:string|null,
     *     notes?:string|null
     * }  $data
     */
    public function registerChargePayment(array $data, User $registeredBy): Payment
    {
        $charge = FeeCharge::query()
            ->with('house.condominium')
            ->findOrFail($data['fee_charge_id']);

        $paymentMethod = $this->paymentMethodResolver->resolve(
            $data['condominium_payment_method_id'] ?? null,
            $charge->house->condominium,
        );

        return DB::transaction(function () use ($data, $paymentMethod, $registeredBy): Payment {
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

            $this->refreshChargeBalance($charge);

            return $payment;
        });
    }

    /**
     * @param  list<string>  $periods
     * @param  array{
     *     condominium_payment_method_id?:int|null,
     *     reference?:string|null,
     *     paid_at?:string|null,
     *     notes?:string|null
     * }  $data
     */
    public function registerAdvancePayment(House $house, array $periods, array $data, User $registeredBy): PaymentBatch
    {
        $house->loadMissing('condominium');
        $paymentMethod = $this->paymentMethodResolver->resolve(
            $data['condominium_payment_method_id'] ?? null,
            $house->condominium,
        );
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now();

        return DB::transaction(function () use ($house, $periods, $data, $paymentMethod, $paidAt, $registeredBy): PaymentBatch {
            $charges = collect($periods)
                ->map(fn ($period) => $this->monthlyFeeChargeGenerator->createForHousePeriod($house->loadMissing('condominium'), $period))
                ->filter(fn (FeeCharge $charge) => (float) $charge->balance > 0)
                ->values();

            if ($charges->isEmpty()) {
                abort(422, 'No existen saldos pendientes para adelantar en esos periodos.');
            }

            $batch = PaymentBatch::query()->create([
                'house_id' => $house->id,
                'registered_by' => $registeredBy->id,
                'total_amount' => $charges->sum(fn (FeeCharge $charge) => (float) $charge->balance),
                'payment_method_id' => $paymentMethod?->payment_method_id,
                'condominium_payment_method_id' => $paymentMethod?->id,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? 'Pago adelantado de alicuotas.',
            ]);

            $charges->each(function (FeeCharge $charge) use ($batch, $data, $paidAt, $paymentMethod, $registeredBy): void {
                $lockedCharge = FeeCharge::query()->lockForUpdate()->findOrFail($charge->id);

                Payment::query()->create([
                    'payment_batch_id' => $batch->id,
                    'fee_charge_id' => $lockedCharge->id,
                    'house_id' => $lockedCharge->house_id,
                    'registered_by' => $registeredBy->id,
                    'amount' => $lockedCharge->balance,
                    'paid_at' => $paidAt,
                    'payment_method_id' => $paymentMethod?->payment_method_id,
                    'condominium_payment_method_id' => $paymentMethod?->id,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? 'Pago adelantado de alicuota '.$lockedCharge->period.'.',
                ]);

                $this->refreshChargeBalance($lockedCharge);
            });

            return $batch;
        });
    }

    public function refreshChargeBalance(FeeCharge $charge): void
    {
        $paidAmount = $charge->payments()->sum('amount');
        $balance = max(0, (float) $charge->amount - (float) $paidAmount);

        $charge->forceFill([
            'paid_amount' => $paidAmount,
            'balance' => $balance,
            'status' => $balance <= 0 ? 'paid' : 'partial',
        ])->save();
    }
}
