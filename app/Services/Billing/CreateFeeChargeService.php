<?php

namespace App\Services\Billing;

use App\Models\Billing\FeeCharge;
use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

class CreateFeeChargeService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{house_id:int|string, period:string, amount:int|float|string, due_date?:string|null, description?:string|null}  $data
     */
    public function create(array $data, User $createdBy, ?Request $request = null): FeeCharge
    {
        $house = House::query()->findOrFail($data['house_id']);

        $charge = FeeCharge::query()->create([
            ...$data,
            'paid_amount' => 0,
            'balance' => $data['amount'],
            'status' => 'pending',
        ]);

        $this->audit->record(
            action: 'fee_charge.created',
            module: 'billing',
            condominiumId: $house->condominium_id,
            user: $createdBy,
            entity: $charge,
            description: 'Alicuota creada manualmente para casa '.$house->code.'.',
            newValues: [
                'period' => $charge->period,
                'amount' => $charge->amount,
                'house_code' => $house->code,
            ],
            request: $request,
        );

        return $charge;
    }
}
