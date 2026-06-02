<?php

namespace App\Http\Requests\Resident;

use App\Http\Requests\ApiFormRequest;
use App\Models\Condominium\House;

class PreviewAdvancePaymentRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $house = $this->route('house');

        if (! $house instanceof House || ! $this->canPay($house)) {
            abort(403, 'No autorizado para pagar alicuotas de esta casa.');
        }

        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'from_period' => ['nullable', 'date_format:Y-m'],
        ];
    }

    private function canPay(House $house): bool
    {
        $membership = $this->user()
            ?->houses()
            ->where('houses.id', $house->id)
            ->wherePivotNotNull('approved_at')
            ->exists();

        return (bool) $membership && $this->user()?->hasHousePermission('resident.payments.create', $house->id);
    }
}
