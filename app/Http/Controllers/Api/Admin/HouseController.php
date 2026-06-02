<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Services\Audit\AuditLogger;
use App\Transformers\HouseTransformer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HouseController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request)
    {
        $houses = House::query()
            ->with([
                'condominium',
                'ownerUsers' => fn ($query) => $query->orderByPivot('is_primary', 'desc'),
                'administratorUsers' => fn ($query) => $query->orderByPivot('is_primary', 'desc'),
            ])
            ->when(! $request->user()->isSeniorAdmin(), fn ($query) => $query->whereIn('condominium_id', $this->managedCondominiumIds($request->user())))
            ->when($request->integer('condominium_id'), fn ($query, $id) => $query->where('condominium_id', $id))
            ->latest()
            ->paginate(20);

        return $this->responder
            ->success($houses, [HouseTransformer::class, 'transform'])
            ->message('Casas obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'exists:condominiums,id'],
            'house_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $request->input('condominium_id')),
            ],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:30'],
        ], [
            'house_number.unique' => 'Ya existe una casa con este numero en el condominio.',
        ]);

        $this->abortUnlessCanManageCondominium($request->user(), (int) $data['condominium_id'], 'houses.manage');

        $condominium = Condominium::query()->findOrFail($data['condominium_id']);
        $code = House::generateCode($condominium, $data['house_number']);

        if (House::query()->where('condominium_id', $condominium->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'house_number' => ['Ya existe una casa con este numero en el condominio.'],
            ]);
        }

        $house = House::query()->create([
            'condominium_id' => $condominium->id,
            'code' => $code,
            'house_number' => $data['house_number'],
            'address_reference' => $data['address_reference'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        $audit->record(
            action: 'house.created',
            module: 'houses',
            condominiumId: $condominium->id,
            user: $request->user(),
            entity: $house,
            description: 'Casa '.$house->code.' creada.',
            newValues: $house->only(['code', 'house_number', 'address_reference', 'status']),
            request: $request,
        );

        return $this->responder
            ->success($house, [HouseTransformer::class, 'transform'], 201)
            ->message('Casa creada correctamente.')
            ->respond();
    }

    public function show(Request $request, House $house)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'houses.manage');

        return $this->responder
            ->success($house->load(['condominium', 'users', 'ownerUsers', 'administratorUsers']), [HouseTransformer::class, 'transform'])
            ->message('Casa obtenida correctamente.')
            ->respond();
    }

    public function update(Request $request, House $house, AuditLogger $audit)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'houses.manage');

        $data = $request->validate([
            'house_number' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $house->condominium_id)->ignore($house),
            ],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:30'],
        ], [
            'house_number.unique' => 'Ya existe una casa con este numero en el condominio.',
        ]);

        $condominium = $house->condominium()->firstOrFail();
        $houseNumber = $data['house_number'] ?? $house->house_number;
        $code = House::generateCode($condominium, $houseNumber);

        if (House::query()
            ->where('condominium_id', $condominium->id)
            ->where('code', $code)
            ->whereKeyNot($house->id)
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'house_number' => ['Ya existe una casa con este numero en el condominio.'],
            ]);
        }

        $oldValues = $house->only(['code', 'house_number', 'address_reference', 'status']);

        $house->update([
            'code' => $code,
            'house_number' => $houseNumber,
            'address_reference' => $data['address_reference'] ?? $house->address_reference,
            'status' => $data['status'] ?? $house->status,
        ]);

        $audit->record(
            action: 'house.updated',
            module: 'houses',
            condominiumId: $house->condominium_id,
            user: $request->user(),
            entity: $house,
            description: 'Casa '.$house->code.' actualizada.',
            oldValues: $oldValues,
            newValues: $house->only(['code', 'house_number', 'address_reference', 'status']),
            request: $request,
        );

        return $this->responder
            ->success($house, [HouseTransformer::class, 'transform'])
            ->message('Casa actualizada correctamente.')
            ->respond();
    }

}
