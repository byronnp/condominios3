<?php

namespace App\Services\Condominium;

use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreateHouseService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{condominium_id:int|string, house_number:string, address_reference?:string|null, status?:string|null}  $data
     */
    public function create(array $data, User $createdBy, ?Request $request = null): House
    {
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

        $this->audit->record(
            action: 'house.created',
            module: 'houses',
            condominiumId: $house->condominium_id,
            user: $createdBy,
            entity: $house,
            description: 'Casa '.$house->code.' creada.',
            newValues: $house->only(['code', 'house_number', 'address_reference', 'status']),
            request: $request,
        );

        return $house;
    }
}
