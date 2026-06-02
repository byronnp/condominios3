<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\House\StoreHouseRequest;
use App\Http\Requests\Api\Admin\House\UpdateHouseRequest;
use App\Models\Condominium\House;
use App\Services\Condominium\CreateHouseService;
use App\Services\Condominium\UpdateHouseService;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $houses = House::query()
            ->with([
                'condominium',
                'ownerUsers' => fn ($query) => $query->orderByPivot('is_primary', 'desc'),
                'administratorUsers' => fn ($query) => $query->orderByPivot('is_primary', 'desc'),
            ])
            ->when(! $request->user()->isSeniorAdmin(), fn ($query) => $query->whereIn('condominium_id', $this->managedCondominiumIds($request->user())))
            ->when($request->integer('condominium_id'), fn ($query, $id) => $query->where('condominium_id', $id))
            ->latest()
            ->paginate(20);

        return $this->responder
            ->success($houses, [HouseTransformer::class, 'transform'])
            ->message('Casas obtenidas correctamente.')
            ->respond();
    }

    public function store(StoreHouseRequest $request, CreateHouseService $houses): JsonResponse
    {
        $data = $request->validated();

        $this->abortUnlessCanManageCondominium($request->user(), (int) $data['condominium_id'], 'houses.manage');
        $house = $houses->create($data, $request->user(), $request);

        return $this->responder
            ->success($house, [HouseTransformer::class, 'transform'], 201)
            ->message('Casa creada correctamente.')
            ->respond();
    }

    public function show(Request $request, House $house): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'houses.manage');

        return $this->responder
            ->success($house->load(['condominium', 'users', 'ownerUsers', 'administratorUsers']), [HouseTransformer::class, 'transform'])
            ->message('Casa obtenida correctamente.')
            ->respond();
    }

    public function update(UpdateHouseRequest $request, House $house, UpdateHouseService $houses): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'houses.manage');

        $house = $houses->update($house, $request->validated(), $request->user(), $request);

        return $this->responder
            ->success($house, [HouseTransformer::class, 'transform'])
            ->message('Casa actualizada correctamente.')
            ->respond();
    }
}
