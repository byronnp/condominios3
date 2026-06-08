<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Transformers\RoleTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->abortUnlessCanManageRoles($request);

        $condominiumId = $request->integer('condominium_id') ?: null;
        $includeGlobal = $request->boolean('include_global', true);

        $roles = Role::query()
            ->with(['condominium', 'permissions'])
            ->when($request->input('scope'), fn ($query, $scope) => $query->where('scope', $scope))
            ->when($condominiumId, fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('condominium_id', $condominiumId)
                    ->when($includeGlobal, fn ($query) => $query->orWhereNull('condominium_id'))))
            ->when(! $condominiumId && $request->boolean('global_only'), fn ($query) => $query->whereNull('condominium_id'))
            ->orderBy('scope')
            ->orderBy('condominium_id')
            ->orderBy('name')
            ->get();

        return $this->responder
            ->success($roles, [RoleTransformer::class, 'transform'])
            ->message('Roles obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        $this->abortUnlessCanManageRoles($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'string', Rule::in(['system', 'condominium', 'resident'])],
            'condominium_id' => ['nullable', 'exists:condominiums,id'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $this->validateCondominiumScope($data);
        $this->validateCodeIsAvailable($data['code'], $data['condominium_id'] ?? null);

        $role = Role::query()->create([
            'condominium_id' => $data['condominium_id'] ?? null,
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
            ->success($role->load(['condominium', 'permissions']), [RoleTransformer::class, 'transform'], 201)
            ->message('Rol creado correctamente.')
            ->respond();
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->abortUnlessCanManageRoles($request);

        return $this->responder
            ->success($role->load(['condominium', 'permissions']), [RoleTransformer::class, 'transform'])
            ->message('Rol obtenido correctamente.')
            ->respond();
    }

    public function update(Request $request, Role $role): JsonResponse
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
            'condominium_id' => [$role->is_system ? 'prohibited' : 'nullable', 'exists:condominiums,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $nextData = array_merge($role->only(['code', 'scope', 'condominium_id']), $data);
        $this->validateCondominiumScope($nextData);
        $this->validateCodeIsAvailable($nextData['code'], $nextData['condominium_id'] ?? null, $role->id);

        $role->fill($data)->save();

        return $this->responder
            ->success($role->load(['condominium', 'permissions']), [RoleTransformer::class, 'transform'])
            ->message('Rol actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Role $role): JsonResponse
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

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->abortUnlessCanManageRoles($request);

        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return $this->responder
            ->success($role->load(['condominium', 'permissions']), [RoleTransformer::class, 'transform'])
            ->message('Permisos del rol actualizados correctamente.')
            ->respond();
    }

    private function abortUnlessCanManageRoles(Request $request): void
    {
        if (! $request->user()->hasPermission('roles.manage')) {
            abort(403, 'No autorizado para administrar roles.');
        }
    }

    /**
     * @param  array{scope:string, condominium_id?:int|string|null}  $data
     */
    private function validateCondominiumScope(array $data): void
    {
        if (($data['scope'] ?? null) === Permission::SCOPE_SYSTEM && ! empty($data['condominium_id'])) {
            throw ValidationException::withMessages([
                'condominium_id' => ['Los roles de sistema deben ser globales.'],
            ]);
        }
    }

    private function validateCodeIsAvailable(string $code, int|string|null $condominiumId, ?int $ignoreRoleId = null): void
    {
        $exists = Role::query()
            ->where('code', $code)
            ->when($condominiumId, fn ($query) => $query->where('condominium_id', $condominiumId), fn ($query) => $query->whereNull('condominium_id'))
            ->when($ignoreRoleId, fn ($query) => $query->whereKeyNot($ignoreRoleId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['Ya existe un rol con este codigo para el mismo contexto.'],
            ]);
        }
    }
}
