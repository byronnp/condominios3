<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Transformers\PermissionTransformer;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()->hasPermission('roles.manage')) {
            return $this->responder->error('No autorizado para administrar roles.', 403)->respond();
        }

        $permissions = Permission::query()
            ->when($request->input('scope'), fn ($query, $scope) => $query->where('scope', $scope))
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        return $this->responder
            ->success($permissions, [PermissionTransformer::class, 'transform'])
            ->message('Permisos obtenidos correctamente.')
            ->respond();
    }
}
