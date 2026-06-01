<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Billing\FeeCharge;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\MonthlyFeeChargeGenerator;
use App\Transformers\FeeChargeTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeeChargeController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $charges = FeeCharge::query()
            ->with('house.condominium')
            ->when(! $request->user()->isSeniorAdmin(), function ($query) use ($request): void {
                $query->whereHas('house', fn ($houseQuery) => $houseQuery->whereIn('condominium_id', $this->managedCondominiumIds($request->user())));
            })
            ->when($request->input('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->input('period'), fn ($query, $period) => $query->where('period', $period))
            ->latest()
            ->paginate(20);

        return $this->responder
            ->success($charges, [FeeChargeTransformer::class, 'transform'])
            ->message('Alicuotas obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'house_id' => ['required', 'exists:houses,id'],
            'period' => [
                'required',
                'date_format:Y-m',
                Rule::unique('fee_charges', 'period')->where('house_id', $request->input('house_id')),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $house = House::query()->findOrFail($data['house_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'fees.manage');

        $charge = FeeCharge::query()->create([
            ...$data,
            'paid_amount' => 0,
            'balance' => $data['amount'],
            'status' => 'pending',
        ]);

        $audit->record(
            action: 'fee_charge.created',
            module: 'billing',
            condominiumId: $house->condominium_id,
            user: $request->user(),
            entity: $charge,
            description: 'Alicuota creada manualmente para casa '.$house->code.'.',
            newValues: [
                'period' => $charge->period,
                'amount' => $charge->amount,
                'house_code' => $house->code,
            ],
            request: $request,
        );

        return $this->responder
            ->success($charge, [FeeChargeTransformer::class, 'transform'], 201)
            ->message('Alicuota creada correctamente.')
            ->respond();
    }

    public function generateMonth(Request $request, MonthlyFeeChargeGenerator $generator, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'exists:condominiums,id'],
            'period' => ['required', 'date_format:Y-m'],
            'due_date' => ['nullable', 'date'],
        ]);

        $condominium = Condominium::query()->findOrFail($data['condominium_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'fees.manage');

        $result = $generator->generateForCondominium(
            $condominium,
            $data['period'],
            $data['due_date'] ?? null,
        );

        $audit->record(
            action: 'fee_charge.generated',
            module: 'billing',
            condominiumId: $condominium->id,
            user: $request->user(),
            description: 'Alicuotas mensuales generadas para periodo '.$data['period'].'.',
            newValues: $result,
            request: $request,
        );

        return $this->responder
            ->success($result)
            ->message('Alicuotas mensuales generadas correctamente.')
            ->respond();
    }
}
