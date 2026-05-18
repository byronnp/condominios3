<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Services\Audit\AuditLogger;
use App\Transformers\HouseInvitationTransformer;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    public function store(Request $request, House $house, AuditLogger $audit): JsonResponse
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para invitar usuarios a esta casa.', 403)->respond();
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'relationship_type_id' => ['required', 'exists:catalog_items,id'],
            'can_view_balance' => ['sometimes', 'boolean'],
            'can_view_payments' => ['sometimes', 'boolean'],
            'can_make_payments' => ['sometimes', 'boolean'],
            'can_receive_notifications' => ['sometimes', 'boolean'],
            'can_invite_users' => ['sometimes', 'boolean'],
        ]);
        $relationshipType = $this->relationshipType($data['relationship_type_id'], ['spouse', 'family', 'tenant', 'representative']);

        $invitation = HouseInvitation::query()->create([
            'house_id' => $house->id,
            'email' => $data['email'],
            'relationship_type_id' => $relationshipType->id,
            'token' => (string) Str::uuid(),
            'can_view_balance' => $data['can_view_balance'] ?? true,
            'can_view_payments' => $data['can_view_payments'] ?? true,
            'can_make_payments' => $data['can_make_payments'] ?? false,
            'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
            'can_invite_users' => $data['can_invite_users'] ?? false,
            'invited_by' => $request->user()->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $audit->record(
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

    public function accept(Request $request, string $token, AuditLogger $audit): JsonResponse
    {
        $invitation = HouseInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now())
            ->firstOrFail();

        if (strtolower($request->user()->email) !== strtolower($invitation->email)) {
            return $this->responder->error('Esta invitacion pertenece a otro correo.', 403)->respond();
        }

        $invitation->house->users()->syncWithoutDetaching([
            $request->user()->id => [
                'relationship_type_id' => $invitation->relationship_type_id,
                'can_view_balance' => $invitation->can_view_balance,
                'can_view_payments' => $invitation->can_view_payments,
                'can_make_payments' => $invitation->can_make_payments,
                'can_receive_notifications' => $invitation->can_receive_notifications,
                'can_invite_users' => $invitation->can_invite_users,
                'is_primary' => false,
                'approved_at' => Carbon::now(),
                'approved_by' => $invitation->invited_by,
            ],
        ]);

        $invitation->forceFill([
            'accepted_by' => $request->user()->id,
            'accepted_at' => Carbon::now(),
        ])->save();

        $audit->record(
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
            if ($request->user()->isSeniorAdmin()) {
                return true;
            }

            return $request->user()
                ->managedCondominiums()
                ->where('condominiums.id', $house->condominium_id)
                ->wherePivot('can_manage_invitations', true)
                ->wherePivotNotNull('approved_at')
                ->exists();
        }

        return $request->user()
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivot('can_invite_users', true)
            ->wherePivotNotNull('approved_at')
            ->exists();
    }

    /**
     * @param  list<string>  $allowedCodes
     */
    private function relationshipType(int $id, array $allowedCodes): CatalogItem
    {
        return CatalogItem::query()
            ->whereKey($id)
            ->whereIn('code', $allowedCodes)
            ->whereHas('catalog', fn ($query) => $query->where('code', 'house_relationship_types'))
            ->firstOrFail();
    }
}
