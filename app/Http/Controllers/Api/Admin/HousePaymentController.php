<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Condominium\House;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HousePaymentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request, House $house): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'payments.manage');

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
