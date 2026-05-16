<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Models\Condominium\House;
use App\Models\User;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function store(Request $request): JsonResponse
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
            'relationship' => ['required', Rule::in(['owner', 'spouse', 'family', 'tenant', 'representative'])],
            'is_primary' => ['sometimes', 'boolean'],
            'can_view_balance' => ['sometimes', 'boolean'],
            'can_view_payments' => ['sometimes', 'boolean'],
            'can_make_payments' => ['sometimes', 'boolean'],
            'can_receive_notifications' => ['sometimes', 'boolean'],
            'can_invite_users' => ['sometimes', 'boolean'],
        ]);

        $house = House::query()->findOrFail($data['house_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'can_manage_residents');

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
                'relationship' => $data['relationship'],
                'is_primary' => $data['is_primary'] ?? $data['relationship'] === 'owner',
                'can_view_balance' => $data['can_view_balance'] ?? true,
                'can_view_payments' => $data['can_view_payments'] ?? true,
                'can_make_payments' => $data['can_make_payments'] ?? $data['relationship'] === 'owner',
                'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
                'can_invite_users' => $data['can_invite_users'] ?? $data['relationship'] === 'owner',
                'approved_at' => Carbon::now(),
                'approved_by' => $request->user()->id,
            ],
        ]);

        return $this->responder
            ->success($user->load(['houses', 'identificationType']), [UserTransformer::class, 'transform'], 201)
            ->message('Residente asignado a la casa correctamente.')
            ->respond();
    }
}
