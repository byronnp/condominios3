<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Menu\MenuBuilder;
use App\Transformers\MenuTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request, MenuBuilder $menuBuilder): JsonResponse
    {
        return $this->responder
            ->success($menuBuilder->forUser($request->user()), [MenuTransformer::class, 'transform'])
            ->message('Menus del usuario obtenidos correctamente.')
            ->respond();
    }
}
