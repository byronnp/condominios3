<?php

namespace App\Services\Resident;

use App\Models\Auth\Role;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HouseInvitationService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{email:string, relationship_type_id:int|string, role_id?:int|string|null, can_receive_notifications?:bool}  $data
     */
    public function create(House $house, array $data, User $invitedBy, ?Request $request = null): HouseInvitation
    {
        $relationshipType = $this->relationshipType((int) $data['relationship_type_id'], ['spouse', 'family', 'tenant', 'representative']);

        $invitation = HouseInvitation::query()->create([
            'house_id' => $house->id,
            'email' => $data['email'],
            'relationship_type_id' => $relationshipType->id,
            'role_id' => $data['role_id'] ?? Role::idForCode(Role::RESIDENT_VIEWER),
            'token' => (string) Str::uuid(),
            'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
            'invited_by' => $invitedBy->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $this->audit->record(
            action: 'resident.invited',
            module: 'residents',
            condominiumId: $house->condominium_id,
            user: $invitedBy,
            entity: $invitation,
            description: 'Invitacion enviada para casa '.$house->code.'.',
            newValues: [
                'email' => $invitation->email,
                'house_code' => $house->code,
                'relationship_type' => $relationshipType->name,
            ],
            request: $request,
        );

        return $invitation;
    }

    public function accept(string $token, User $acceptedBy, ?Request $request = null): HouseInvitation
    {
        $invitation = HouseInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->firstOrFail();

        if (strtolower($acceptedBy->email) !== strtolower($invitation->email)) {
            abort(403, 'Esta invitacion pertenece a otro correo.');
        }

        $invitation->house->users()->syncWithoutDetaching([
            $acceptedBy->id => [
                'relationship_type_id' => $invitation->relationship_type_id,
                'role_id' => $invitation->role_id ?? Role::idForCode(Role::RESIDENT_VIEWER),
                'can_receive_notifications' => $invitation->can_receive_notifications,
                'is_primary' => false,
                'approved_at' => Carbon::now(),
                'approved_by' => $invitation->invited_by,
            ],
        ]);

        $invitation->forceFill([
            'accepted_by' => $acceptedBy->id,
            'accepted_at' => Carbon::now(),
        ])->save();

        $this->audit->record(
            action: 'resident.invitation_accepted',
            module: 'residents',
            condominiumId: $invitation->house->condominium_id,
            user: $acceptedBy,
            entity: $invitation,
            description: 'Invitacion aceptada para casa '.$invitation->house->code.'.',
            newValues: [
                'accepted_by' => $acceptedBy->id,
                'house_code' => $invitation->house->code,
            ],
            request: $request,
        );

        return $invitation;
    }

    /**
     * @param  list<string>  $allowedCodes
     */
    private function relationshipType(int $id, array $allowedCodes): CatalogItem
    {
        return CatalogItem::query()
            ->whereKey($id)
            ->whereIn('code', $allowedCodes)
            ->whereHas('catalog', fn ($query) => $query->where('code', 'house_relationship_types'))
            ->firstOrFail();
    }
}
