<?php

namespace App\Services\Resident;

use App\Models\Auth\Role;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class HouseInvitationService
{
    /**
     * @param  array{
     *     email:string,
     *     relationship_type_id:int,
     *     role_id?:int|null,
     *     can_receive_notifications?:bool|null
     * }  $data
     */
    public function create(House $house, array $data, User $inviter): HouseInvitation
    {
        $relationshipType = $this->relationshipType($data['relationship_type_id'], [
            'spouse',
            'family',
            'tenant',
            'representative',
        ]);

        return HouseInvitation::query()->create([
            'house_id' => $house->id,
            'email' => $data['email'],
            'relationship_type_id' => $relationshipType->id,
            'role_id' => $data['role_id'] ?? Role::idForCode(Role::RESIDENT_VIEWER),
            'token' => (string) Str::uuid(),
            'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
            'invited_by' => $inviter->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    public function findAcceptableInvitation(string $token): HouseInvitation
    {
        return HouseInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->firstOrFail();
    }

    public function accept(HouseInvitation $invitation, User $user): HouseInvitation
    {
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            abort(403, 'Esta invitacion pertenece a otro correo.');
        }

        $invitation->house->users()->syncWithoutDetaching([
            $user->id => [
                'relationship_type_id' => $invitation->relationship_type_id,
                'role_id' => $invitation->role_id ?? Role::idForCode(Role::RESIDENT_VIEWER),
                'can_receive_notifications' => $invitation->can_receive_notifications,
                'is_primary' => false,
                'approved_at' => Carbon::now(),
                'approved_by' => $invitation->invited_by,
            ],
        ]);

        $invitation->forceFill([
            'accepted_by' => $user->id,
            'accepted_at' => Carbon::now(),
        ])->save();

        return $invitation;
    }

    /**
     * @param  list<string>  $allowedCodes
     */
    public function relationshipType(int $id, array $allowedCodes): CatalogItem
    {
        return CatalogItem::query()
            ->whereKey($id)
            ->whereIn('code', $allowedCodes)
            ->whereHas('catalog', fn ($query) => $query->where('code', 'house_relationship_types'))
            ->firstOrFail();
    }
}
