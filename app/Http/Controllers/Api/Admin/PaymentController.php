<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
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
            ->with(['house.condominium', 'feeCharge', 'registeredBy'])
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fee_charge_id' => ['required', 'exists:fee_charges,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:40'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $charge = FeeCharge::query()->with('house')->findOrFail($data['fee_charge_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $charge->house->condominium_id, 'can_manage_payments');

        $payment = DB::transaction(function () use ($data, $request): Payment {
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
                'payment_method' => $data['payment_method'] ?? null,
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

        return $this->responder
            ->success($payment->load('feeCharge'), [PaymentTransformer::class, 'transform'], 201)
            ->message('Pago registrado correctamente.')
            ->respond();
    }
}
