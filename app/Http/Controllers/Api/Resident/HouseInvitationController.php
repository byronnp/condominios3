<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Auth\Permission;
use App\Models\Condominium\House;
use App\Services\Resident\HouseInvitationService;
use App\Support\RoleRules;
use App\Transformers\HouseInvitationTransformer;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseInvitationController extends Controller
{
    public function index(Request $request, House $house): JsonResponse
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para ver invitaciones de esta casa.', 403)->respond();
        }

        return $this->responder
            ->success($house->invitations()->with('relationshipType')->latest()->get(), [HouseInvitationTransformer::class, 'transform'])
            ->message('Invitaciones obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request, House $house, HouseInvitationService $invitations): JsonResponse
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para invitar usuarios a esta casa.', 403)->respond();
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'relationship_type_id' => ['required', 'exists:catalog_items,id'],
            'role_id' => ['sometimes', RoleRules::activeInScopeForCondominium(Permission::SCOPE_RESIDENT, $house->condominium_id)],
            'can_receive_notifications' => ['sometimes', 'boolean'],
        ]);
        $invitation = $invitations->create($house, $data, $request->user(), $request);

        return $this->responder
            ->success($invitation->load('relationshipType'), [HouseInvitationTransformer::class, 'transform'], 201)
            ->message('Invitacion creada correctamente.')
            ->respond();
    }

    public function accept(Request $request, string $token, HouseInvitationService $invitations): JsonResponse
    {
        $invitation = $invitations->accept($token, $request->user(), $request);

        return $this->responder
            ->success($invitation->house->load('condominium'), [HouseTransformer::class, 'transform'])
            ->message('Invitacion aceptada correctamente.')
            ->respond();
    }

    private function canInvite(Request $request, House $house): bool
    {
        if ($request->user()->isAdmin()) {
            if ($request->user()->hasPermission('system.manage')) {
                return true;
            }

            return $request->user()->hasPermission('invitations.manage', $house->condominium_id);
        }

        $membership = $request->user()
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivotNotNull('approved_at')
            ->exists();

        return $membership && $request->user()->hasHousePermission('resident.invitations.create', $house->id);
    }
}
