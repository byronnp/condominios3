<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentBatch;
use App\Models\Condominium\House;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\MonthlyFeeChargeGenerator;
use App\Services\Billing\PaymentMethodResolver;
use App\Transformers\PaymentBatchTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdvancePaymentController extends Controller
{
    public function preview(Request $request, House $house, MonthlyFeeChargeGenerator $generator): JsonResponse
    {
        $this->abortUnlessCanPay($request, $house);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'from_period' => ['nullable', 'date_format:Y-m'],
        ]);

        $periods = $this->periods($house, $data['months'], $data['from_period'] ?? null);
        $items = collect($periods)
            ->map(fn ($period) => $generator->previewForHousePeriod($house->loadMissing('condominium'), $period))
            ->values();

        return $this->responder
            ->success([
                'house_id' => $house->id,
                'months' => $data['months'],
                'from_period' => $periods[0],
                'to_period' => $periods[array_key_last($periods)],
                'items' => $items,
                'total' => $items->sum(fn ($item) => (float) $item['balance']),
            ])
            ->message('Adelanto calculado correctamente.')
            ->respond();
    }

    public function store(Request $request, House $house, MonthlyFeeChargeGenerator $generator, PaymentMethodResolver $paymentMethodResolver, AuditLogger $audit): JsonResponse
    {
        $this->abortUnlessCanPay($request, $house);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'from_period' => ['nullable', 'date_format:Y-m'],
            'condominium_payment_method_id' => ['nullable', 'exists:condominium_payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $house->loadMissing('condominium');
        $paymentMethod = $paymentMethodResolver->resolve($data['condominium_payment_method_id'] ?? null, $house->condominium);
        $periods = $this->periods($house, $data['months'], $data['from_period'] ?? null);
        $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : Carbon::now();

        $batch = DB::transaction(function () use ($house, $generator, $data, $paymentMethod, $periods, $paidAt, $request): PaymentBatch {
            $charges = collect($periods)
                ->map(fn ($period) => $generator->createForHousePeriod($house->loadMissing('condominium'), $period))
                ->filter(fn (FeeCharge $charge) => (float) $charge->balance > 0)
                ->values();

            if ($charges->isEmpty()) {
                abort(422, 'No existen saldos pendientes para adelantar en esos periodos.');
            }

            $total = $charges->sum(fn (FeeCharge $charge) => (float) $charge->balance);

            $batch = PaymentBatch::query()->create([
                'house_id' => $house->id,
                'registered_by' => $request->user()->id,
                'total_amount' => $total,
                'payment_method_id' => $paymentMethod?->payment_method_id,
                'condominium_payment_method_id' => $paymentMethod?->id,
                'reference' => $data['reference'] ?? null,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? 'Pago adelantado de alicuotas.',
            ]);

            $charges->each(function (FeeCharge $charge) use ($batch, $data, $paidAt, $paymentMethod, $request): void {
                $lockedCharge = FeeCharge::query()->lockForUpdate()->findOrFail($charge->id);
                $amount = $lockedCharge->balance;

                Payment::query()->create([
                    'payment_batch_id' => $batch->id,
                    'fee_charge_id' => $lockedCharge->id,
                    'house_id' => $lockedCharge->house_id,
                    'registered_by' => $request->user()->id,
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
        $audit->record(
            action: 'payment.advance_created',
            module: 'payments',
            condominiumId: $house->condominium_id,
            user: $request->user(),
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

        return $this->responder
            ->success($batch, [PaymentBatchTransformer::class, 'transform'], 201)
            ->message('Pago adelantado registrado correctamente.')
            ->respond();
    }

    private function abortUnlessCanPay(Request $request, House $house): void
    {
        $membership = $request->user()
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivot('can_make_payments', true)
            ->wherePivotNotNull('approved_at')
            ->first();

        if (! $membership) {
            abort(403, 'No autorizado para pagar alicuotas de esta casa.');
        }
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
