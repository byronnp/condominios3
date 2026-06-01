<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Condominium\House;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousePaymentController extends Controller
{
    public function index(Request $request, House $house): JsonResponse
    {
        $membership = $request->user()
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivotNotNull('approved_at')
            ->first();

        if (! $membership || ! $request->user()->hasHousePermission('resident.payments.view', $house->id)) {
            return $this->responder
                ->error('No autorizado para ver los pagos de esta casa.', 403)
                ->respond();
        }

        $payments = $house->payments()
            ->with(['feeCharge', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod'])
            ->latest('paid_at')
            ->paginate(20);

        return $this->responder
            ->success($payments, [PaymentTransformer::class, 'transform'])
            ->message('Pagos de la casa obtenidos correctamente.')
            ->respond();
    }
}
