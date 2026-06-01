<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\PaymentMethodResolver;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request, PaymentMethodResolver $paymentMethodResolver, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'fee_charge_id' => ['required', 'exists:fee_charges,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'condominium_payment_method_id' => ['nullable', 'exists:condominium_payment_methods,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $charge = FeeCharge::query()->with('house.condominium')->findOrFail($data['fee_charge_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $charge->house->condominium_id, 'payments.manage');
        $paymentMethod = $paymentMethodResolver->resolve($data['condominium_payment_method_id'] ?? null, $charge->house->condominium);

        $payment = DB::transaction(function () use ($data, $paymentMethod, $request): Payment {
            $charge = FeeCharge::query()->lockForUpdate()->findOrFail($data['fee_charge_id']);

            if ((float) $data['amount'] > (float) $charge->balance) {
                abort(422, 'El pago no puede ser mayor al saldo pendiente.');
            }

            $payment = Payment::query()->create([
                'fee_charge_id' => $charge->id,
                'house_id' => $charge->house_id,
                'registered_by' => $request->user()->id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'] ?? Carbon::now(),
                'payment_method_id' => $paymentMethod?->payment_method_id,
                'condominium_payment_method_id' => $paymentMethod?->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $paidAmount = $charge->payments()->sum('amount');
            $balance = max(0, (float) $charge->amount - (float) $paidAmount);

            $charge->forceFill([
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'status' => $balance <= 0 ? 'paid' : 'partial',
            ])->save();

            return $payment;
        });

        $payment->load(['feeCharge', 'house', 'paymentMethod', 'condominiumPaymentMethod.paymentMethod']);
        $audit->record(
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
