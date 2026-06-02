<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Payment\StorePaymentRequest;
use App\Models\Billing\FeeCharge;
use App\Services\Billing\RegisterPaymentService;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->with(['house.condominium', 'feeCharge', 'registeredBy', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod'])
            ->when(! $request->user()->isSeniorAdmin(), function ($query) use ($request): void {
                $query->whereHas('house', fn ($houseQuery) => $houseQuery->whereIn('condominium_id', $this->managedCondominiumIds($request->user())));
            })
            ->when($request->integer('house_id'), fn ($query, $id) => $query->where('house_id', $id))
            ->latest('paid_at')
            ->paginate(20);

        return $this->responder
            ->success($payments, [PaymentTransformer::class, 'transform'])
            ->message('Pagos obtenidos correctamente.')
            ->respond();
    }

    public function store(StorePaymentRequest $request, RegisterPaymentService $payments): JsonResponse
    {
        $data = $request->validated();

        $charge = FeeCharge::query()->with('house.condominium')->findOrFail($data['fee_charge_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $charge->house->condominium_id, 'payments.manage');
        $payment = $payments->register($data, $request->user(), $request);

        return $this->responder
            ->success($payment, [PaymentTransformer::class, 'transform'], 201)
            ->message('Pago registrado correctamente.')
            ->respond();
    }
}
