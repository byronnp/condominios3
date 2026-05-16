<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Transformers\HouseInvitationTransformer;
use App\Transformers\HouseTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HouseInvitationController extends Controller
{
    public function index(Request $request, House $house): JsonResponse
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para ver invitaciones de esta casa.', 403)->respond();
        }

        return $this->responder
            ->success($house->invitations()->latest()->get(), [HouseInvitationTransformer::class, 'transform'])
            ->message('Invitaciones obtenidas correctamente.')
            ->respond();
    }

    public function store(Request $request, House $house): JsonResponse
    {
        if (! $this->canInvite($request, $house)) {
            return $this->responder->error('No autorizado para invitar usuarios a esta casa.', 403)->respond();
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'relationship' => ['required', Rule::in(['spouse', 'family', 'tenant', 'representative'])],
            'can_view_balance' => ['sometimes', 'boolean'],
            'can_view_payments' => ['sometimes', 'boolean'],
            'can_make_payments' => ['sometimes', 'boolean'],
            'can_receive_notifications' => ['sometimes', 'boolean'],
            'can_invite_users' => ['sometimes', 'boolean'],
        ]);

        $invitation = HouseInvitation::query()->create([
            'house_id' => $house->id,
            'email' => $data['email'],
            'relationship' => $data['relationship'],
            'token' => (string) Str::uuid(),
            'can_view_balance' => $data['can_view_balance'] ?? true,
            'can_view_payments' => $data['can_view_payments'] ?? true,
            'can_make_payments' => $data['can_make_payments'] ?? false,
            'can_receive_notifications' => $data['can_receive_notifications'] ?? true,
            'can_invite_users' => $data['can_invite_users'] ?? false,
            'invited_by' => $request->user()->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        return $this->responder
            ->success($invitation, [HouseInvitationTransformer::class, 'transform'], 201)
            ->message('Invitacion creada correctamente.')
            ->respond();
    }

    public function accept(Request $request, string $token): JsonResponse
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
                'relationship' => $invitation->relationship,
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
}
