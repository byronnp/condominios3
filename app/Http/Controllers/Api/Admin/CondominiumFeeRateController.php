<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Billing\CondominiumFeeRate;
use App\Models\Condominium\Condominium;
use App\Services\Audit\AuditLogger;
use App\Transformers\CondominiumFeeRateTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CondominiumFeeRateController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $rates = CondominiumFeeRate::query()
            ->with('condominium')
            ->when(! $request->user()->isSeniorAdmin(), function ($query) use ($request): void {
                $query->whereIn('condominium_id', $this->managedCondominiumIds($request->user()));
            })
            ->when($request->integer('condominium_id'), fn ($query, $id) => $query->where('condominium_id', $id))
            ->latest('starts_at')
            ->paginate(20);

        return $this->responder
            ->success($rates, [CondominiumFeeRateTransformer::class, 'transform'])
            ->message('Tarifas obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'exists:condominiums,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'starts_at' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! Carbon::parse($data['starts_at'])->isStartOfMonth()) {
            abort(422, 'La tarifa debe iniciar el primer dia del mes.');
        }

        $condominium = Condominium::query()->findOrFail($data['condominium_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'fees.manage');

        $isActive = $data['is_active'] ?? true;

        $closedRate = null;
        $rate = DB::transaction(function () use ($data, $isActive, &$closedRate): CondominiumFeeRate {
            if ($isActive) {
                $closedRate = $this->closeOpenPreviousRate($data['condominium_id'], $data['starts_at']);

                if ($this->hasOverlappingRate($data)) {
                    abort(422, 'Ya existe una tarifa vigente que cruza con ese rango de fechas.');
                }
            }

            return CondominiumFeeRate::query()->create([
                ...$data,
                'ends_at' => null,
                'is_active' => $isActive,
            ]);
        });

        if ($closedRate) {
            $audit->record(
                action: 'fee_rate.closed',
                module: 'billing',
                condominiumId: $condominium->id,
                user: $request->user(),
                entity: $closedRate,
                description: 'Tarifa anterior de alicuota cerrada automaticamente.',
                oldValues: ['ends_at' => null],
                newValues: ['ends_at' => $closedRate->ends_at?->toDateString()],
                request: $request,
            );
        }

        $audit->record(
            action: 'fee_rate.created',
            module: 'billing',
            condominiumId: $condominium->id,
            user: $request->user(),
            entity: $rate,
            description: 'Nueva tarifa de alicuota creada desde '.$rate->starts_at?->toDateString().'.',
            newValues: [
                'amount' => $rate->amount,
                'starts_at' => $rate->starts_at?->toDateString(),
                'ends_at' => $rate->ends_at?->toDateString(),
                'is_active' => $rate->is_active,
            ],
            request: $request,
        );

        return $this->responder
            ->success($rate->load('condominium'), [CondominiumFeeRateTransformer::class, 'transform'], 201)
            ->message('Tarifa creada correctamente.')
            ->respond();
    }

    /**
     * @param  array{condominium_id:int, starts_at:string, ends_at?:string|null}  $data
     */
    private function hasOverlappingRate(array $data): bool
    {
        $newEnd = $data['ends_at'] ?? '9999-12-31';

        return CondominiumFeeRate::query()
            ->where('condominium_id', $data['condominium_id'])
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', $newEnd)
            ->where(function ($query) use ($data): void {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $data['starts_at']);
            })
            ->exists();
    }

    private function closeOpenPreviousRate(int $condominiumId, string $startsAt): ?CondominiumFeeRate
    {
        $previousRate = CondominiumFeeRate::query()
            ->where('condominium_id', $condominiumId)
            ->where('is_active', true)
            ->whereNull('ends_at')
            ->whereDate('starts_at', '<', $startsAt)
            ->latest('starts_at')
            ->lockForUpdate()
            ->first();

        if (! $previousRate) {
            return null;
        }

        $previousRate->forceFill([
            'ends_at' => Carbon::parse($startsAt)->subDay()->toDateString(),
        ])->save();

        return $previousRate;
    }
}
