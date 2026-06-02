<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Condominium\House;
use App\Services\Billing\AdvancePaymentService;
use App\Transformers\PaymentBatchTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancePaymentController extends Controller
{
    public function preview(Request $request, House $house, AdvancePaymentService $advancePayments): JsonResponse
    {
        $this->abortUnlessCanPay($request, $house);

        $data = $request->validate([
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'from_period' => ['nullable', 'date_format:Y-m'],
        ]);

        return $this->responder
            ->success($advancePayments->preview($house, $data))
            ->message('Adelanto calculado correctamente.')
            ->respond();
    }

    public function store(Request $request, House $house, AdvancePaymentService $advancePayments): JsonResponse
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

        $batch = $advancePayments->create($house, $data, $request->user(), $request);

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
            ->wherePivotNotNull('approved_at')
            ->first();

        if (! $membership || ! $request->user()->hasHousePermission('resident.payments.create', $house->id)) {
            abort(403, 'No autorizado para pagar alicuotas de esta casa.');
        }
    }
}
