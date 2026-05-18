<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Transformers\CondominiumTransformer;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CondominiumController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            $houses = House::query()
                ->with('condominium')
                ->whereIn('condominium_id', $this->managedCondominiumIds($request->user()))
                ->orderBy('code')
                ->paginate(20);

            return $this->responder
                ->success($houses, [HouseTransformer::class, 'transform'])
                ->message('Casas del condominio obtenidas correctamente.')
                ->respond();
        }

        return $this->responder
            ->success($this->scopeCondominiumsFor($request->user(), Condominium::query())
                ->with('houses')
                ->withCount('houses')
                ->latest()
                ->paginate(20), [CondominiumTransformer::class, 'transform'])
            ->message('Condominios obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede crear condominios.', 403)->respond();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->responder
            ->success(Condominium::query()->create($data), [CondominiumTransformer::class, 'transform'], 201)
            ->message('Condominio creado correctamente.')
            ->respond();
    }

    public function show(Request $request, Condominium $condominium): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'can_manage_houses');

        return $this->responder
            ->success($condominium->load('houses')->loadCount('houses'), [CondominiumTransformer::class, 'transform'])
            ->message('Condominio obtenido correctamente.')
            ->respond();
    }

    public function update(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede editar condominios.', 403)->respond();
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $condominium->update($data);

        return $this->responder
            ->success($condominium, [CondominiumTransformer::class, 'transform'])
            ->message('Condominio actualizado correctamente.')
            ->respond();
    }
}
