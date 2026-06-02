<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\StoreHouseInvitationRequest;
use App\Models\Condominium\House;
use App\Services\Audit\AuditLogger;
use App\Services\Resident\HouseInvitationService;
use App\Transformers\HouseInvitationTransformer;
use App\Transformers\HouseTransformer;
use Illuminate\Http\Request;

class HouseInvitationController extends Controller
{
    public function __construct(
        private readonly HouseInvitationService $invitations,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request, House $house)
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para ver invitaciones de esta casa.', 403)->respond();
        }

        return $this->responder
            ->success($house->invitations()->with('relationshipType')->latest()->get(), [HouseInvitationTransformer::class, 'transform'])
            ->message('Invitaciones obtenidas correctamente.')
            ->respond();
    }

    public function store(StoreHouseInvitationRequest $request, House $house)
    {
        $data = $request->validated();

        $invitation = $this->invitations->create($house, $data, $request->user());
        $relationshipType = $invitation->relationshipType()->firstOrFail();

        $this->audit->record(
            action: 'resident.invited',
            module: 'residents',
            condominiumId: $house->condominium_id,
            user: $request->user(),
            entity: $invitation,
            description: 'Invitacion enviada para casa '.$house->code.'.',
            newValues: [
                'email' => $invitation->email,
                'house_code' => $house->code,
                'relationship_type' => $relationshipType->name,
            ],
            request: $request,
        );

        return $this->responder
            ->success($invitation->load('relationshipType'), [HouseInvitationTransformer::class, 'transform'], 201)
            ->message('Invitacion creada correctamente.')
            ->respond();
    }

    public function accept(Request $request, string $token)
    {
        $invitation = $this->invitations->accept(
            $this->invitations->findAcceptableInvitation($token),
            $request->user(),
        );

        $this->audit->record(
            action: 'resident.invitation_accepted',
            module: 'residents',
            condominiumId: $invitation->house->condominium_id,
            user: $request->user(),
            entity: $invitation,
            description: 'Invitacion aceptada para casa '.$invitation->house->code.'.',
            newValues: [
                'accepted_by' => $request->user()->id,
                'house_code' => $invitation->house->code,
            ],
            request: $request,
        );

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
