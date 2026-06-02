<?php

namespace App\Services\Billing;

use App\Models\Condominium\House;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdvancePaymentPlanner
{
    public function __construct(
        private readonly MonthlyFeeChargeGenerator $monthlyFeeChargeGenerator,
    ) {}

    /**
     * @return array{
     *     house_id:int,
     *     months:int,
     *     from_period:string,
     *     to_period:string,
     *     items:Collection<int, array<string, mixed>>,
     *     total:float
     * }
     */
    public function preview(House $house, int $months, ?string $fromPeriod = null): array
    {
        $periods = $this->periods($house, $months, $fromPeriod);
        $items = collect($periods)
            ->map(fn ($period) => $this->monthlyFeeChargeGenerator->previewForHousePeriod($house->loadMissing('condominium'), $period))
            ->values();

        return [
            'house_id' => $house->id,
            'months' => $months,
            'from_period' => $periods[0],
            'to_period' => $periods[array_key_last($periods)],
            'items' => $items,
            'total' => $items->sum(fn ($item) => (float) $item['balance']),
        ];
    }

    /**
     * @return list<string>
     */
    public function periods(House $house, int $months, ?string $fromPeriod = null): array
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
