<?php

namespace App\Services\Condominium;

use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateHouseService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{house_number?:string, address_reference?:string|null, status?:string|null}  $data
     */
    public function update(House $house, array $data, User $updatedBy, ?Request $request = null): House
    {
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

        $this->audit->record(
            action: 'house.updated',
            module: 'houses',
            condominiumId: $house->condominium_id,
            user: $updatedBy,
            entity: $house,
            description: 'Casa '.$house->code.' actualizada.',
            oldValues: $oldValues,
            newValues: $house->only(['code', 'house_number', 'address_reference', 'status']),
            request: $request,
        );

        return $house;
    }
}
