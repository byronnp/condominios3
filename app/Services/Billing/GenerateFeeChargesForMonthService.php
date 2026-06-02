<?php

namespace App\Services\Billing;

use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class GenerateFeeChargesForMonthService
{
    public function __construct(
        private readonly MonthlyFeeChargeGenerator $generator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{condominium_id:int|string, period:string, due_date?:string|null}  $data
     * @return array{condominium_id:int, condominium_name:string, period:string, amount:string, created:int, skipped:int}
     */
    public function generate(array $data, User $generatedBy, ?Request $request = null): array
    {
        $condominium = Condominium::query()->findOrFail($data['condominium_id']);

        $result = $this->generator->generateForCondominium(
            $condominium,
            $data['period'],
            $data['due_date'] ?? null,
        );

        $this->audit->record(
            action: 'fee_charge.generated',
            module: 'billing',
            condominiumId: $condominium->id,
            user: $generatedBy,
            description: 'Alicuotas mensuales generadas para periodo '.$data['period'].'.',
            newValues: $result,
            request: $request,
        );

        return $result;
    }
}
