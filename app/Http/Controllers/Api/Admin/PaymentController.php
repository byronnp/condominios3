<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\PaymentRegistrationService;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function __construct(
        private readonly PaymentRegistrationService $payments,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
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

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();

        $charge = FeeCharge::query()->with('house.condominium')->findOrFail($data['fee_charge_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $charge->house->condominium_id, 'payments.manage');

        $payment = $this->payments->registerChargePayment($data, $request->user());
        $payment->load(['feeCharge', 'house', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod']);
        $this->audit->record(
            action: 'payment.created',
            module: 'payments',
            condominiumId: $payment->house?->condominium_id,
            user: $request->user(),
            entity: $payment,
            description: 'Pago registrado para casa '.$payment->house?->code.'.',
            newValues: [
                'amount' => $payment->amount,
                'period' => $payment->feeCharge?->period,
                'house_code' => $payment->house?->code,
            ],
            metadata: [
                'reference' => $payment->reference,
                'payment_method' => $payment->condominiumPaymentMethod?->display_name ?? $payment->paymentMethod?->name,
            ],
            request: $request,
        );

        return $this->responder
            ->success($payment, [PaymentTransformer::class, 'transform'], 201)
            ->message('Pago registrado correctamente.')
            ->respond();
    }
}
