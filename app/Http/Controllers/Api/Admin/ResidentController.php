<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Resident\StoreResidentRequest;
use App\Models\Condominium\House;
use App\Services\Resident\AssignResidentToHouseService;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;

class ResidentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function store(StoreResidentRequest $request, AssignResidentToHouseService $residents): JsonResponse
    {
        $data = $request->validated();

        $house = House::query()->findOrFail($data['house_id']);
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'residents.manage');
        $assignment = $residents->assign($data, $request->user(), $request);
        $user = $assignment['user'];

        return $this->responder
            ->success($user->load(['houses', 'identificationType', 'userRole']), [UserTransformer::class, 'transform'], 201)
            ->message('Residente asignado a la casa correctamente.')
            ->respond();
    }
}
