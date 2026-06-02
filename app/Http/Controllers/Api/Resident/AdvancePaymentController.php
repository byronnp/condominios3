<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\PreviewAdvancePaymentRequest;
use App\Http\Requests\Resident\StoreAdvancePaymentRequest;
use App\Models\Condominium\House;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\AdvancePaymentPlanner;
use App\Services\Billing\PaymentRegistrationService;
use App\Transformers\PaymentBatchTransformer;

class AdvancePaymentController extends Controller
{
    public function __construct(
        private readonly AdvancePaymentPlanner $planner,
        private readonly PaymentRegistrationService $payments,
        private readonly AuditLogger $audit,
    ) {}

    public function preview(PreviewAdvancePaymentRequest $request, House $house)
    {
        $data = $request->validated();

        return $this->responder
            ->success($this->planner->preview($house, $data['months'], $data['from_period'] ?? null))
            ->message('Adelanto calculado correctamente.')
            ->respond();
    }

    public function store(
        StoreAdvancePaymentRequest $request,
        House $house
    )
    {
        $data = $request->validated();

        $periods = $this->planner->periods($house, $data['months'], $data['from_period'] ?? null);
        $batch = $this->payments->registerAdvancePayment($house, $periods, $data, $request->user());

        $batch->load(['house', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod', 'payments.feeCharge', 'payments.paymentMethod', 'payments.condominiumPaymentMethod.paymentMethod']);
        $this->audit->record(
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
}
