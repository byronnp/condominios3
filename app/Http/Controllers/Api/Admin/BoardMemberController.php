<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Board\StoreBoardMemberRequest;
use App\Http\Requests\Api\Admin\Board\UpdateBoardMemberRequest;
use App\Models\Board\BoardMember;
use App\Models\Board\BoardTerm;
use App\Services\Board\BoardMemberService;
use App\Transformers\BoardMemberTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardMemberController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function store(StoreBoardMemberRequest $request, BoardTerm $boardTerm, BoardMemberService $members): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $boardTerm->condominium_id, 'board.manage');

        $member = $members->assign($boardTerm->load('condominium'), $request->validated(), $request->user(), $request);

        return $this->responder
            ->success($member, [BoardMemberTransformer::class, 'transform'], 201)
            ->message('Miembro de directiva asignado correctamente.')
            ->respond();
    }

    public function update(UpdateBoardMemberRequest $request, BoardMember $boardMember, BoardMemberService $members): JsonResponse
    {
        $boardMember->load('boardTerm.condominium', 'user');

        $this->abortUnlessCanManageCondominium($request->user(), $boardMember->boardTerm->condominium_id, 'board.manage');

        $member = $members->update($boardMember, $request->validated(), $request->user(), $request);

        return $this->responder
            ->success($member, [BoardMemberTransformer::class, 'transform'])
            ->message('Miembro de directiva actualizado correctamente.')
            ->respond();
    }

    public function destroy(Request $request, BoardMember $boardMember, BoardMemberService $members): JsonResponse
    {
        $boardMember->load('boardTerm.condominium', 'user');

        $this->abortUnlessCanManageCondominium($request->user(), $boardMember->boardTerm->condominium_id, 'board.manage');

        $members->remove($boardMember, $request->user(), $request);

        return $this->responder
            ->success()
            ->message('Miembro de directiva removido correctamente.')
            ->respond();
    }
}
