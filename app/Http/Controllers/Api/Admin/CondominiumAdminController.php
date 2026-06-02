<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCondominiumAdminRequest;
use App\Http\Requests\Admin\UpdateCondominiumAdminRequest;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Services\Condominium\CondominiumAdminService;
use App\Transformers\CondominiumAdminTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Http\Request;

class CondominiumAdminController extends Controller
{
    public function __construct(
        private readonly CondominiumAdminService $admins,
    ) {}

    public function all(Request $request)
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

    public function index(Request $request, Condominium $condominium)
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede ver administradores por condominio.', 403)->respond();
        }

        return $this->responder
            ->success($condominium->administrators()->with(['identificationType', 'userRole'])->get(), [CondominiumAdminTransformer::class, 'transform'])
            ->message('Administradores obtenidos correctamente.')
            ->respond();
    }

    public function store(StoreCondominiumAdminRequest $request, Condominium $condominium)
    {
        $data = $request->validated();

        $user = $this->admins->assign($condominium, $data, $request->user());

        return $this->responder
            ->success($user->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Administrador asignado al condominio correctamente.')
            ->respond();
    }

    public function update(UpdateCondominiumAdminRequest $request, Condominium $condominium, User $admin)
    {
        $data = $request->validated();

        $admin = $this->admins->update($condominium, $admin, $data);

        return $this->responder
            ->success($admin->load(['managedCondominiums', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'])
            ->message('Administrador de condominio actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Condominium $condominium, User $admin)
    {
        if (! $request->user()->hasPermission('admins.manage')) {
            return $this->responder->error('Solo el administrador senior puede eliminar administradores de condominio.', 403)->respond();
        }

        if (! $this->admins->remove($condominium, $admin)) {
            return $this->responder->error('Administrador no asignado al condominio.', 404)->respond();
        }

        return $this->responder
            ->success()
            ->message('Administrador eliminado del condominio correctamente.')
            ->respond();
    }
}
