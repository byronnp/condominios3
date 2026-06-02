<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Condominium\StoreCondominiumRequest;
use App\Http\Requests\Api\Admin\Condominium\UpdateCondominiumRequest;
use App\Models\Catalog\Catalog;
use App\Models\Catalog\CatalogItem;
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
                ->with([
                    'status',
                    'administrators' => fn ($query) => $query->orderBy('name'),
                ])
                ->withCount('houses')
                ->latest()
                ->paginate(20), [CondominiumTransformer::class, 'transform'])
            ->message('Condominios obtenidos correctamente.')
            ->respond();
    }

    public function store(StoreCondominiumRequest $request): JsonResponse
    {
        if (! $request->user()->hasPermission('condominiums.manage')) {
            return $this->responder->error('Solo el administrador senior puede crear condominios.', 403)->respond();
        }

        $data = $request->validated();

        $data['status_id'] ??= $this->condominiumStatusId('active');
        $data['is_active'] = $this->statusCodeFor((int) $data['status_id']) === 'active';

        return $this->responder
            ->success(Condominium::query()->create($data)->load(['status', 'administrators']), [CondominiumTransformer::class, 'transform'], 201)
            ->message('Condominio creado correctamente.')
            ->respond();
    }

    public function show(Request $request, Condominium $condominium): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'houses.manage');

        return $this->responder
            ->success($condominium->load([
                'status',
                'administrators' => fn ($query) => $query->orderBy('name'),
            ])->loadCount('houses'), [CondominiumTransformer::class, 'transform'])
            ->message('Condominio obtenido correctamente.')
            ->respond();
    }

    public function update(UpdateCondominiumRequest $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->hasPermission('condominiums.manage')) {
            return $this->responder->error('Solo el administrador senior puede editar condominios.', 403)->respond();
        }

        $data = $request->validated();

        if (array_key_exists('status_id', $data)) {
            $data['is_active'] = $this->statusCodeFor((int) $data['status_id']) === 'active';
        }

        $condominium->update($data);

        return $this->responder
            ->success($condominium->load([
                'status',
                'administrators' => fn ($query) => $query->orderBy('name'),
            ]), [CondominiumTransformer::class, 'transform'])
            ->message('Condominio actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, Condominium $condominium): JsonResponse
    {
        if (! $request->user()->hasPermission('condominiums.manage')) {
            return $this->responder->error('Solo el administrador senior puede eliminar condominios.', 403)->respond();
        }

        $condominium->forceFill(['is_active' => false])->save();
        $condominium->delete();

        return $this->responder
            ->success()
            ->message('Condominio eliminado correctamente.')
            ->respond();
    }

    private function condominiumStatusCatalogId(): int
    {
        return (int) Catalog::query()->where('code', 'condominium_statuses')->value('id');
    }

    private function condominiumStatusId(string $code): int
    {
        return (int) CatalogItem::query()
            ->where('catalog_id', $this->condominiumStatusCatalogId())
            ->where('code', $code)
            ->value('id');
    }

    private function statusCodeFor(int $statusId): ?string
    {
        return CatalogItem::query()->whereKey($statusId)->value('code');
    }
}
