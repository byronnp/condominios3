<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Models\Condominium\House;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $houses = House::query()
            ->with('condominium')
            ->when(! $request->user()->isSeniorAdmin(), fn ($query) => $query->whereIn('condominium_id', $this->managedCondominiumIds($request->user())))
            ->when($request->integer('condominium_id'), fn ($query, $id) => $query->where('condominium_id', $id))
            ->latest()
            ->paginate(20);

        return $this->responder
            ->success($houses, [HouseTransformer::class, 'transform'])
            ->message('Casas obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'condominium_id' => ['required', 'exists:condominiums,id'],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('houses', 'code')->where('condominium_id', $request->input('condominium_id')),
            ],
            'house_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $request->input('condominium_id')),
            ],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $this->abortUnlessCanManageCondominium($request->user(), (int) $data['condominium_id'], 'can_manage_houses');

        return $this->responder
            ->success(House::query()->create($data), [HouseTransformer::class, 'transform'], 201)
            ->message('Casa creada correctamente.')
            ->respond();
    }

    public function show(Request $request, House $house): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'can_manage_houses');

        return $this->responder
            ->success($house->load(['condominium', 'users']), [HouseTransformer::class, 'transform'])
            ->message('Casa obtenida correctamente.')
            ->respond();
    }

    public function update(Request $request, House $house): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'can_manage_houses');

        $data = $request->validate([
            'code' => [
                'sometimes',
                'string',
                'max:80',
                Rule::unique('houses', 'code')->where('condominium_id', $house->condominium_id)->ignore($house),
            ],
            'house_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('houses', 'house_number')->where('condominium_id', $house->condominium_id)->ignore($house),
            ],
            'address_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:30'],
        ]);

        $house->update($data);

        return $this->responder
            ->success($house, [HouseTransformer::class, 'transform'])
            ->message('Casa actualizada correctamente.')
            ->respond();
    }
}
