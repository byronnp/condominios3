<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Resident\StoreResidentRequest;
use App\Models\Condominium\House;
use App\Services\Resident\AssignResidentToHouseService;
use App\Transformers\HouseResidentTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function indexByHouse(Request $request, House $house): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $house->condominium_id, 'residents.manage');

        $residents = $house->users()
            ->with(['identificationType', 'userRole'])
            ->leftJoin('catalog_items as relationship_types', 'house_user.relationship_type_id', '=', 'relationship_types.id')
            ->leftJoin('roles as house_roles', 'house_user.role_id', '=', 'house_roles.id')
            ->select('users.*')
            ->addSelect([
                'relationship_type_id' => 'relationship_types.id',
                'relationship_type_name' => 'relationship_types.name',
                'house_role_id' => 'house_roles.id',
                'house_role_name' => 'house_roles.name',
            ])
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('users.name')
            ->get();

        return $this->responder
            ->success($residents, [HouseResidentTransformer::class, 'transform'])
            ->message('Residentes de la casa obtenidos correctamente.')
            ->respond();
    }

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
