<?php

namespace App\Services\Billing;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentBatch;
use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdvancePaymentService
{
    public function __construct(
        private readonly MonthlyFeeChargeGenerator $generator,
        private readonly PaymentMethodResolver $paymentMethodResolver,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{months:int, from_period?:string|null}  $data
     * @return array{house_id:int, months:int, from_period:string, to_period:string, items:mixed, total:float}
     */
    public function preview(House $house, array $data): array
    {
        $periods = $this->periods($house, (int) $data['months'], $data['from_period'] ?? null);
        $items = collect($periods)
            ->map(fn ($period) => $this->generator->previewForHousePeriod($house->loadMissing('condominium'), $period))
            ->values();

        return [
            'house_id' => $house->id,
            'months' => (int) $data['months'],
            'from_period' => $periods[0],
            'to_period' => $periods[array_key_last($periods)],
            'items' => $items,
            'total' => $items->sum(fn ($item) => (float) $item['balance']),
        ];
    }

    /**
     * @param  array{months:int, from_period?:string|null, condominium_payment_method_id?:int|string|null, reference?:string|null, paid_at?:string|null, notes?:string|null}  $data
     */
    public function create(House $house, array $data, User $registeredBy, ?Request $request = null): PaymentBatch
    {
        $house->loadMissing('condominium');
        $paymentMethod = $this->paymentMethodResolver->resolve($data['condominium_payment_method_id'] ?? null, $house->condominium);
        $periods = $this->periods($house, (int) $data['months'], $data['from_period'] ?? null);
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now();

        $batch = DB::transaction(function () use ($house, $data, $paymentMethod, $periods, $paidAt, $registeredBy): PaymentBatch {
            $charges = collect($periods)
                ->map(fn ($period) => $this->generator->createForHousePeriod($house->loadMissing('condominium'), $period))
                ->filter(fn (FeeCharge $charge) => (float) $charge->balance > 0)
                ->values();

            if ($charges->isEmpty()) {
                abort(422, 'No existen saldos pendientes para adelantar en esos periodos.');
            }

            $total = $charges->sum(fn (FeeCharge $charge) => (float) $charge->balance);

            $batch = PaymentBatch::query()->create([
                'house_id' => $house->id,
                'registered_by' => $registeredBy->id,
                'total_amount' => $total,
                'payment_method_id' => $paymentMethod?->payment_method_id,
                'condominium_payment_method_id' => $paymentMethod?->id,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? 'Pago adelantado de alicuotas.',
            ]);

            $charges->each(function (FeeCharge $charge) use ($batch, $data, $paidAt, $paymentMethod, $registeredBy): void {
                $lockedCharge = FeeCharge::query()->lockForUpdate()->findOrFail($charge->id);
                $amount = $lockedCharge->balance;

                Payment::query()->create([
                    'payment_batch_id' => $batch->id,
                    'fee_charge_id' => $lockedCharge->id,
                    'house_id' => $lockedCharge->house_id,
                    'registered_by' => $registeredBy->id,
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'payment_method_id' => $paymentMethod?->payment_method_id,
                    'condominium_payment_method_id' => $paymentMethod?->id,
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? 'Pago adelantado de alicuota '.$lockedCharge->period.'.',
                ]);

                $paidAmount = $lockedCharge->payments()->sum('amount');
                $balance = max(0, (float) $lockedCharge->amount - (float) $paidAmount);

                $lockedCharge->forceFill([
                    'paid_amount' => $paidAmount,
                    'balance' => $balance,
                    'status' => $balance <= 0 ? 'paid' : 'partial',
                ])->save();
            });

            return $batch;
        });

        $batch->load(['house', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod', 'payments.feeCharge', 'payments.paymentMethod', 'payments.condominiumPaymentMethod.paymentMethod']);

        $this->audit->record(
            action: 'payment.advance_created',
            module: 'payments',
            condominiumId: $house->condominium_id,
            user: $registeredBy,
            entity: $batch,
            description: 'Pago adelantado registrado para casa '.$house->code.' por '.$data['months'].' meses.',
            newValues: [
                'total_amount' => $batch->total_amount,
                'house_code' => $house->code,
            ],
            metadata: [
                'periods' => $periods,
                'reference' => $batch->reference,
                'payment_method' => $batch->condominiumPaymentMethod?->display_name ?? $batch->paymentMethod?->name,
            ],
            request: $request,
        );

        return $batch;
    }

    /**
     * @return list<string>
     */
    private function periods(House $house, int $months, ?string $fromPeriod): array
    {
        $start = Carbon::createFromFormat('Y-m-d', ($fromPeriod ?? $this->nextPayablePeriod($house)).'-01')->startOfMonth();

        return collect(range(0, $months - 1))
            ->map(fn ($offset) => $start->copy()->addMonthsNoOverflow($offset)->format('Y-m'))
            ->all();
    }

    private function nextPayablePeriod(House $house): string
    {
        $pendingCharge = $house->feeCharges()
            ->where('status', '!=', 'paid')
            ->orderBy('period')
            ->first();

        if ($pendingCharge) {
            return $pendingCharge->period;
        }

        $latestCharge = $house->feeCharges()
            ->orderByDesc('period')
            ->first();

        if ($latestCharge) {
            return Carbon::createFromFormat('Y-m-d', $latestCharge->period.'-01')
                ->addMonthNoOverflow()
                ->format('Y-m');
        }

        return Carbon::now()->format('Y-m');
    }
}
