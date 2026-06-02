<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Board\StoreBoardTermRequest;
use App\Http\Requests\Api\Admin\Board\UpdateBoardTermRequest;
use App\Models\Board\BoardTerm;
use App\Models\Condominium\Condominium;
use App\Services\Board\BoardTermService;
use App\Transformers\BoardTermTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardTermController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'board.view');

        $terms = $condominium->boardTerms()
            ->with(['condominium', 'members.user', 'members.position', 'members.role'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('starts_at')
            ->paginate(20);

        return $this->responder
            ->success($terms, [BoardTermTransformer::class, 'transform'])
            ->message('Directivas obtenidas correctamente.')
            ->respond();
    }

    public function store(StoreBoardTermRequest $request, Condominium $condominium, BoardTermService $terms): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'board.manage');

        $term = $terms->create($condominium, $request->validated(), $request->user(), $request);

        return $this->responder
            ->success($term, [BoardTermTransformer::class, 'transform'], 201)
            ->message('Directiva creada correctamente.')
            ->respond();
    }

    public function show(Request $request, BoardTerm $boardTerm): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $boardTerm->condominium_id, 'board.view');

        return $this->responder
            ->success($boardTerm->load(['condominium', 'members.user', 'members.position', 'members.role']), [BoardTermTransformer::class, 'transform'])
            ->message('Directiva obtenida correctamente.')
            ->respond();
    }

    public function update(UpdateBoardTermRequest $request, BoardTerm $boardTerm, BoardTermService $terms): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $boardTerm->condominium_id, 'board.manage');

        $term = $terms->update($boardTerm, $request->validated(), $request->user(), $request);

        return $this->responder
            ->success($term, [BoardTermTransformer::class, 'transform'])
            ->message('Directiva actualizada correctamente.')
            ->respond();
    }
}
