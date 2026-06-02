<?php

namespace App\Http\Requests\Resident;

use App\Http\Requests\ApiFormRequest;
use App\Models\Condominium\House;
use Illuminate\Validation\Rule;

class StoreHouseInvitationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $house = $this->route('house');

        if (! $house instanceof House || ! $this->canInvite($house)) {
            abort(403, 'No autorizado para invitar usuarios a esta casa.');
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'relationship_type_id' => ['required', 'exists:catalog_items,id'],
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'resident')->where('is_active', true)],
            'can_receive_notifications' => ['sometimes', 'boolean'],
        ];
    }

    private function canInvite(House $house): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            if ($user->hasPermission('system.manage')) {
                return true;
            }

            return $user->hasPermission('invitations.manage', $house->condominium_id);
        }

        $membership = $user
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivotNotNull('approved_at')
            ->exists();

        return $membership && $user->hasHousePermission('resident.invitations.create', $house->id);
    }
}
