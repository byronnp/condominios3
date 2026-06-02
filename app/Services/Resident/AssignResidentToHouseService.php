<?php

namespace App\Services\Resident;

use App\Models\Auth\Role;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AssignResidentToHouseService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{first_name:string, last_name:string, identification_type_id:int|string, identification_number:string, mobile_phone?:string|null, landline_phone?:string|null, email:string, password?:string|null, house_id:int|string, relationship_type_id:int|string, role_id?:int|string|null, is_primary?:bool, can_receive_notifications?:bool}  $data
     * @return array{user:User, house:House, relationship_type:CatalogItem, is_primary:bool}
     */
    public function assign(array $data, User $approvedBy, ?Request $request = null): array
    {
        $house = House::query()->findOrFail($data['house_id']);
        $relationshipType = $this->relationshipType((int) $data['relationship_type_id'], ['owner', 'spouse', 'family', 'tenant', 'representative']);
        $isOwner = $relationshipType->code === 'owner';

        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => User::fullName($data['first_name'], $data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'identification_type_id' => $data['identification_type_id'],
                'identification_number' => $data['identification_number'],
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'landline_phone' => $data['landline_phone'] ?? null,
                'password' => $data['password'] ?? str()->password(16),
                'role' => User::ROLE_RESIDENT,
                'is_active' => true,
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $user->forceFill([
                'name' => User::fullName($data['first_name'], $data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'identification_type_id' => $data['identification_type_id'],
                'identification_number' => $data['identification_number'],
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'landline_phone' => $data['landline_phone'] ?? null,
            ])->save();
        }

        $isPrimary = $data['is_primary'] ?? $isOwner;

        $house->users()->syncWithoutDetaching([
            $user->id => [
                'relationship_type_id' => $relationshipType->id,
                'role_id' => $data['role_id'] ?? Role::idForCode($isOwner ? Role::RESIDENT_OWNER : Role::RESIDENT_VIEWER),
                'is_primary' => $isPrimary,
                'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
                'approved_at' => Carbon::now(),
                'approved_by' => $approvedBy->id,
            ],
        ]);

        $this->audit->record(
            action: 'resident.assigned',
            module: 'residents',
            condominiumId: $house->condominium_id,
            user: $approvedBy,
            entity: $user,
            description: 'Residente '.$user->name.' asignado a casa '.$house->code.'.',
            newValues: [
                'resident_id' => $user->id,
                'house_id' => $house->id,
                'house_code' => $house->code,
                'relationship_type' => $relationshipType->name,
                'is_primary' => $isPrimary,
            ],
            request: $request,
        );

        return [
            'user' => $user,
            'house' => $house,
            'relationship_type' => $relationshipType,
            'is_primary' => $isPrimary,
        ];
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
