<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Transformers\CondominiumAdminTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CondominiumAdminController extends Controller
{
    public function all(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede ver todos los administradores.', 403)->respond();
        }

        $admins = User::query()
            ->withRole(User::ROLE_CONDOMINIUM_ADMIN)
            ->with(['identificationType', 'managedCondominiums', 'userRole'])
            ->orderBy('name')
            ->paginate(20);

        return $this->responder
            ->success($admins, [UserTransformer::class, 'transform'])
            ->message('Administradores de condominios obtenidos correctamente.')
            ->respond();
    }

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede ver administradores por condominio.', 403)->respond();
        }

        return $this->responder
            ->success($condominium->administrators()->with(['identificationType', 'userRole'])->get(), [CondominiumAdminTransformer::class, 'transform'])
            ->message('Administradores obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
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
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'condominium')->where('is_active', true)],
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
                'role_id' => $data['role_id'] ?? Role::idForCode(User::ROLE_CONDOMINIUM_ADMIN),
                'approved_at' => Carbon::now(),
                'approved_by' => $request->user()->id,
                'deleted_at' => null,
            ],
        ]);

        return $this->responder
            ->success($user->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Administrador asignado al condominio correctamente.')
            ->respond();
    }

    public function update(Request $request, Condominium $condominium, User $admin): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede editar administradores de condominio.', 403)->respond();
        }

        if (! $condominium->administrators()->where('users.id', $admin->id)->exists()) {
            return $this->responder->error('Administrador no asignado al condominio.', 404)->respond();
        }

        if ($admin->isSeniorAdmin()) {
            return $this->responder->error('Un administrador senior no debe asignarse como administrador de condominio.', 422)->respond();
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'identification_type_id' => ['sometimes', 'exists:catalog_items,id'],
            'identification_number' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('users', 'identification_number')->ignore($admin->id),
            ],
            'mobile_phone' => ['nullable', 'string', 'max:30'],
            'landline_phone' => ['nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['sometimes', Rule::exists('roles', 'id')->where('scope', 'condominium')->where('is_active', true)],
        ]);

        DB::transaction(function () use ($admin, $condominium, $data): void {
            $userData = collect($data)
                ->only([
                    'first_name',
                    'last_name',
                    'identification_type_id',
                    'identification_number',
                    'mobile_phone',
                    'landline_phone',
                    'email',
                    'password',
                ])
                ->all();

            if (($userData['password'] ?? null) === null) {
                unset($userData['password']);
            }

            if (array_key_exists('first_name', $userData) || array_key_exists('last_name', $userData)) {
                $firstName = $userData['first_name'] ?? $admin->first_name;
                $lastName = $userData['last_name'] ?? $admin->last_name;
                $userData['name'] = User::fullName($firstName, $lastName);
            }

            if ($userData !== []) {
                $admin->forceFill($userData)->save();
            }

            if (! $admin->isCondominiumAdmin()) {
                $admin->forceFill(['role' => User::ROLE_CONDOMINIUM_ADMIN])->save();
            }

            $pivotData = collect($data)
                ->only([
                    'role_id',
                ])
                ->all();

            if ($pivotData !== []) {
                $condominium->administrators()->updateExistingPivot($admin->id, $pivotData);
            }
        });

        return $this->responder
            ->success($admin->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'])
            ->message('Administrador de condominio actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Condominium $condominium, User $admin): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede eliminar administradores de condominio.', 403)->respond();
        }

        $updated = DB::table('condominium_user')
            ->where('condominium_id', $condominium->id)
            ->where('user_id', $admin->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        if (! $updated) {
            return $this->responder->error('Administrador no asignado al condominio.', 404)->respond();
        }

        return $this->responder
            ->success()
            ->message('Administrador eliminado del condominio correctamente.')
            ->respond();
    }
}
