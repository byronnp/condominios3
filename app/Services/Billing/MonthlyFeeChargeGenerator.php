<?php

namespace App\Services\Billing;

use App\Models\Billing\CondominiumFeeRate;
use App\Models\Billing\FeeCharge;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyFeeChargeGenerator
{
    /**
     * @return array{condominium_id:int, condominium_name:string, period:string, amount:string, created:int, skipped:int}
     */
    public function generateForCondominium(Condominium $condominium, string $period, ?string $dueDate = null): array
    {
        $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $rate = $this->activeRateFor($condominium, $periodStart);

        if (! $rate) {
            abort(422, 'No existe una tarifa activa para este periodo.');
        }

        $dueAt = $dueDate
            ? Carbon::parse($dueDate)->toDateString()
            : $periodStart->copy()->endOfMonth()->toDateString();

        return DB::transaction(function () use ($condominium, $period, $rate, $dueAt): array {
            $created = 0;
            $skipped = 0;

            $condominium->houses()
                ->where('status', 'active')
                ->orderBy('id')
                ->each(function ($house) use ($period, $rate, $dueAt, &$created, &$skipped): void {
                    $charge = FeeCharge::query()->firstOrCreate([
                        'house_id' => $house->id,
                        'period' => $period,
                    ], [
                        'amount' => $rate->amount,
                        'paid_amount' => 0,
                        'balance' => $rate->amount,
                        'due_date' => $dueAt,
                        'status' => 'pending',
                        'description' => 'Alicuota '.$period,
                    ]);

                    $charge->wasRecentlyCreated ? $created++ : $skipped++;
                });

            return [
                'condominium_id' => $condominium->id,
                'condominium_name' => $condominium->name,
                'period' => $period,
                'amount' => $rate->amount,
                'created' => $created,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * @return array{period:string, amount:string, fee_charge_id:int|null, status:string, balance:string}
     */
    public function previewForHousePeriod(House $house, string $period): array
    {
        $charge = $house->feeCharges()->where('period', $period)->first();

        if ($charge) {
            return [
                'period' => $period,
                'amount' => $charge->amount,
                'fee_charge_id' => $charge->id,
                'status' => $charge->status,
                'balance' => $charge->balance,
            ];
        }

        $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $rate = $this->activeRateFor($house->condominium, $periodStart);

        if (! $rate) {
            abort(422, 'No existe una tarifa activa para el periodo '.$period.'.');
        }

        return [
            'period' => $period,
            'amount' => $rate->amount,
            'fee_charge_id' => null,
            'status' => 'pending',
            'balance' => $rate->amount,
        ];
    }

    public function createForHousePeriod(House $house, string $period, ?string $dueDate = null): FeeCharge
    {
        $periodStart = Carbon::createFromFormat('Y-m-d', $period.'-01')->startOfMonth();
        $dueAt = $dueDate
            ? Carbon::parse($dueDate)->toDateString()
            : $periodStart->copy()->endOfMonth()->toDateString();
        $rate = $this->activeRateFor($house->condominium, $periodStart);

        if (! $rate) {
            abort(422, 'No existe una tarifa activa para el periodo '.$period.'.');
        }

        return FeeCharge::query()->firstOrCreate([
            'house_id' => $house->id,
            'period' => $period,
        ], [
            'amount' => $rate->amount,
            'paid_amount' => 0,
            'balance' => $rate->amount,
            'due_date' => $dueAt,
            'status' => 'pending',
            'description' => 'Alicuota '.$period,
        ]);
    }

    private function activeRateFor(Condominium $condominium, Carbon $periodStart): ?CondominiumFeeRate
    {
        return $condominium->feeRates()
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $periodStart->toDateString())
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $periodStart->toDateString());
            })
            ->latest('starts_at')
            ->first();
    }
}
