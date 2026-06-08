<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Services\Condominium\CondominiumAdminService;
use App\Support\RoleRules;
use App\Transformers\CondominiumAdminTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, Condominium $condominium, CondominiumAdminService $admins): JsonResponse
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
            'role_id' => ['sometimes', RoleRules::activeInScopeForCondominium('condominium', $condominium->id)],
        ]);

        $user = $admins->assign($condominium, $data, $request->user());

        return $this->responder
            ->success($user->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Administrador asignado al condominio correctamente.')
            ->respond();
    }

    public function update(Request $request, Condominium $condominium, User $admin, CondominiumAdminService $admins): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede editar administradores de condominio.', 403)->respond();
        }

        if (! $condominium->administrators()->where('users.id', $admin->id)->exists()) {
            return $this->responder->error('Administrador no asignado al condominio.', 404)->respond();
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
            'role_id' => ['sometimes', RoleRules::activeInScopeForCondominium('condominium', $condominium->id)],
        ]);

        $admin = $admins->update($condominium, $admin, $data);

        return $this->responder
            ->success($admin->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'])
            ->message('Administrador de condominio actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Condominium $condominium, User $admin, CondominiumAdminService $admins): JsonResponse
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede eliminar administradores de condominio.', 403)->respond();
        }

        if (! $admins->remove($condominium, $admin)) {
            return $this->responder->error('Administrador no asignado al condominio.', 404)->respond();
        }

        return $this->responder
            ->success()
            ->message('Administrador eliminado del condominio correctamente.')
            ->respond();
    }
}
