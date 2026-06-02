<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Transformers\RoleTransformer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $this->abortUnlessCanManageRoles($request);

        $roles = Role::query()
            ->with('permissions')
            ->when($request->input('scope'), fn ($query, $scope) => $query->where('scope', $scope))
            ->orderBy('scope')
            ->orderBy('name')
            ->get();

        return $this->responder
            ->success($roles, [RoleTransformer::class, 'transform'])
            ->message('Roles obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request)
    {
        $this->abortUnlessCanManageRoles($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', Rule::in(['system', 'condominium', 'resident'])],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::query()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'scope' => $data['scope'],
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids']);
        }

        return $this->responder
            ->success($role->load('permissions'), [RoleTransformer::class, 'transform'], 201)
            ->message('Rol creado correctamente.')
            ->respond();
    }

    public function show(Request $request, Role $role)
    {
        $this->abortUnlessCanManageRoles($request);

        return $this->responder
            ->success($role->load('permissions'), [RoleTransformer::class, 'transform'])
            ->message('Rol obtenido correctamente.')
            ->respond();
    }

    public function update(Request $request, Role $role)
    {
        $this->abortUnlessCanManageRoles($request);

        $data = $request->validate([
            'code' => [
                $role->is_system ? 'prohibited' : 'sometimes',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique('roles', 'code')->ignore($role),
            ],
            'name' => ['sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => [$role->is_system ? 'prohibited' : 'sometimes', 'string', Rule::in(['system', 'condominium', 'resident'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role->fill($data)->save();

        return $this->responder
            ->success($role->load('permissions'), [RoleTransformer::class, 'transform'])
            ->message('Rol actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Role $role)
    {
        $this->abortUnlessCanManageRoles($request);

        if ($role->is_system) {
            return $this->responder->error('No se puede eliminar un rol del sistema.', 422)->respond();
        }

        $role->delete();

        return $this->responder
            ->success()
            ->message('Rol eliminado correctamente.')
            ->respond();
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $this->abortUnlessCanManageRoles($request);

        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return $this->responder
            ->success($role->load('permissions'), [RoleTransformer::class, 'transform'])
            ->message('Permisos del rol actualizados correctamente.')
            ->respond();
    }

    private function abortUnlessCanManageRoles(Request $request): void
    {
        if (! $request->user()->hasPermission('roles.manage')) {
            abort(403, 'No autorizado para administrar roles.');
        }
    }
}
