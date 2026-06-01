<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $existingUser = User::query()->where('email', $request->input('email'))->first();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'identification_type_id' => ['required', 'exists:catalog_items,id'],
            'identification_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique('users', 'identification_number')->ignore($existingUser?->id),
            ],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'landline_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'house_id' => ['required', 'exists:houses,id'],
            'relationship_type_id' => ['required', 'exists:catalog_items,id'],
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'resident')->where('is_active', true)],
            'is_primary' => ['sometimes', 'boolean'],
            'can_receive_notifications' => ['sometimes', 'boolean'],
        ]);

        $house = House::query()->findOrFail($data['house_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'residents.manage');
        $relationshipType = $this->relationshipType($data['relationship_type_id'], ['owner', 'spouse', 'family', 'tenant', 'representative']);
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

        $house->users()->syncWithoutDetaching([
            $user->id => [
                'relationship_type_id' => $relationshipType->id,
                'role_id' => $data['role_id'] ?? Role::idForCode($isOwner ? Role::RESIDENT_OWNER : Role::RESIDENT_VIEWER),
                'is_primary' => $data['is_primary'] ?? $isOwner,
                'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
                'approved_at' => Carbon::now(),
                'approved_by' => $request->user()->id,
            ],
        ]);

        $audit->record(
            action: 'resident.assigned',
            module: 'residents',
            condominiumId: $house->condominium_id,
            user: $request->user(),
            entity: $user,
            description: 'Residente '.$user->name.' asignado a casa '.$house->code.'.',
            newValues: [
                'resident_id' => $user->id,
                'house_id' => $house->id,
                'house_code' => $house->code,
                'relationship_type' => $relationshipType->name,
                'is_primary' => $data['is_primary'] ?? $isOwner,
            ],
            request: $request,
        );

        return $this->responder
            ->success($user->load(['houses', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Residente asignado a la casa correctamente.')
            ->respond();
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
