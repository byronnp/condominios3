<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CondominiumAdminController extends Controller
{
    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede ver administradores por condominio.', 403)->respond();
        }

        return $this->responder
            ->success($condominium->administrators()->with('identificationType')->get(), [UserTransformer::class, 'transform'])
            ->message('Administradores obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede asignar administradores.', 403)->respond();
        }

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
            'can_manage_houses' => ['sometimes', 'boolean'],
            'can_manage_residents' => ['sometimes', 'boolean'],
            'can_manage_fees' => ['sometimes', 'boolean'],
            'can_manage_payments' => ['sometimes', 'boolean'],
            'can_manage_invitations' => ['sometimes', 'boolean'],
        ]);

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
                'role' => User::ROLE_CONDOMINIUM_ADMIN,
                'is_active' => true,
            ],
        );

        if ($user->isSeniorAdmin()) {
            return $this->responder->error('Un administrador senior no debe asignarse como administrador de condominio.', 422)->respond();
        }

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

        if (! $user->isCondominiumAdmin()) {
            $user->forceFill(['role' => User::ROLE_CONDOMINIUM_ADMIN])->save();
        }

        $condominium->administrators()->syncWithoutDetaching([
            $user->id => [
                'role' => User::ROLE_CONDOMINIUM_ADMIN,
                'can_manage_houses' => $data['can_manage_houses'] ?? true,
                'can_manage_residents' => $data['can_manage_residents'] ?? true,
                'can_manage_fees' => $data['can_manage_fees'] ?? true,
                'can_manage_payments' => $data['can_manage_payments'] ?? true,
                'can_manage_invitations' => $data['can_manage_invitations'] ?? true,
                'approved_at' => Carbon::now(),
                'approved_by' => $request->user()->id,
                'deleted_at' => null,
            ],
        ]);

        return $this->responder
            ->success($user->load(['managedCondominiums', 'identificationType']), [UserTransformer::class, 'transform'], 201)
            ->message('Administrador asignado al condominio correctamente.')
            ->respond();
    }
}
