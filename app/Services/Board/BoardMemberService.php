<?php

namespace App\Services\Board;

use App\Models\Board\BoardMember;
use App\Models\Board\BoardTerm;
use App\Models\Catalog\CatalogItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BoardMemberService
{
    private const UNIQUE_POSITION_CODES = [
        'president',
        'vice_president',
        'treasurer',
        'secretary',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function assign(BoardTerm $term, array $data, User $assignedBy, ?Request $request = null): BoardMember
    {
        return DB::transaction(function () use ($term, $data, $assignedBy, $request): BoardMember {
            $user = $this->resolveUser($data);
            $startsAt = $data['starts_at'] ?? $term->starts_at->toDateString();
            $endsAt = $data['ends_at'] ?? null;
            $isActive = $data['is_active'] ?? true;

            $this->abortUnlessDatesFitTerm($term, $startsAt, $endsAt);
            $this->abortIfUserAlreadyAssigned($term, $user->id);
            $this->abortIfPositionIsAlreadyActive($term, (int) $data['position_id'], (bool) $isActive);

            $member = $term->members()->create([
                'user_id' => $user->id,
                'position_id' => $data['position_id'],
                'role_id' => $data['role_id'] ?? null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => $isActive,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncCondominiumAccess($term, $user, $data['role_id'] ?? null, $assignedBy);

            $this->audit->record(
                action: 'board_member.assigned',
                module: 'board',
                condominiumId: $term->condominium_id,
                user: $assignedBy,
                entity: $member,
                description: 'Miembro asignado a directiva.',
                newValues: $member->toArray(),
                request: $request,
            );

            return $member->load(['boardTerm.condominium', 'user', 'position', 'role']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BoardMember $member, array $data, User $updatedBy, ?Request $request = null): BoardMember
    {
        return DB::transaction(function () use ($member, $data, $updatedBy, $request): BoardMember {
            $term = $member->boardTerm;
            $oldValues = $member->toArray();

            $startsAt = $data['starts_at'] ?? $member->starts_at->toDateString();
            $endsAt = array_key_exists('ends_at', $data) ? $data['ends_at'] : $member->ends_at?->toDateString();
            $positionId = (int) ($data['position_id'] ?? $member->position_id);
            $isActive = (bool) ($data['is_active'] ?? $member->is_active);

            $this->abortUnlessDatesFitTerm($term, $startsAt, $endsAt);
            $this->abortIfPositionIsAlreadyActive($term, $positionId, $isActive, $member->id);

            $member->update($data);

            if (array_key_exists('role_id', $data)) {
                $this->syncCondominiumAccess($term, $member->user, $data['role_id'], $updatedBy);
            }

            $this->audit->record(
                action: 'board_member.updated',
                module: 'board',
                condominiumId: $term->condominium_id,
                user: $updatedBy,
                entity: $member,
                description: 'Miembro de directiva actualizado.',
                oldValues: $oldValues,
                newValues: $member->fresh()->toArray(),
                request: $request,
            );

            return $member->load(['boardTerm.condominium', 'user', 'position', 'role']);
        });
    }

    public function remove(BoardMember $member, User $removedBy, ?Request $request = null): void
    {
        DB::transaction(function () use ($member, $removedBy, $request): void {
            $term = $member->boardTerm;
            $oldValues = $member->toArray();

            $member->forceFill([
                'is_active' => false,
                'ends_at' => $member->ends_at ?? Carbon::today()->toDateString(),
            ])->save();

            $member->delete();

            $this->audit->record(
                action: 'board_member.removed',
                module: 'board',
                condominiumId: $term->condominium_id,
                user: $removedBy,
                entity: $member,
                description: 'Miembro removido de directiva.',
                oldValues: $oldValues,
                newValues: $member->toArray(),
                request: $request,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveUser(array $data): User
    {
        if (! empty($data['user_id'])) {
            return User::query()->findOrFail($data['user_id']);
        }

        $user = User::query()->firstOrCreate(
            ['email' => $data['email']],
            [
                'name' => User::fullName($data['first_name'], $data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'identification_type_id' => $data['identification_type_id'] ?? null,
                'identification_number' => $data['identification_number'] ?? null,
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'landline_phone' => $data['landline_phone'] ?? null,
                'password' => $data['password'] ?? str()->password(16),
                'role' => User::ROLE_CONDOMINIUM_ADMIN,
                'is_active' => true,
            ],
        );

        if (! $user->wasRecentlyCreated) {
            $user->forceFill([
                'name' => User::fullName($data['first_name'], $data['last_name']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'identification_type_id' => $data['identification_type_id'] ?? $user->identification_type_id,
                'identification_number' => $data['identification_number'] ?? $user->identification_number,
                'mobile_phone' => $data['mobile_phone'] ?? $user->mobile_phone,
                'landline_phone' => $data['landline_phone'] ?? $user->landline_phone,
            ])->save();
        }

        return $user;
    }

    private function abortUnlessDatesFitTerm(BoardTerm $term, string $startsAt, ?string $endsAt): void
    {
        $start = Carbon::parse($startsAt)->startOfDay();
        $end = $endsAt ? Carbon::parse($endsAt)->startOfDay() : null;

        if ($start->lt($term->starts_at->startOfDay())) {
            abort(422, 'La fecha de inicio del miembro no puede ser menor al inicio de la gestion.');
        }

        if ($end && $end->gt($term->ends_at->startOfDay())) {
            abort(422, 'La fecha de fin del miembro no puede ser mayor al fin de la gestion.');
        }

        if ($end && $end->lt($start)) {
            abort(422, 'La fecha de fin del miembro no puede ser menor a la fecha de inicio.');
        }
    }

    private function abortIfUserAlreadyAssigned(BoardTerm $term, int $userId): void
    {
        $exists = $term->members()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            abort(422, 'Este usuario ya esta asignado como miembro activo de esta directiva.');
        }
    }

    private function abortIfPositionIsAlreadyActive(BoardTerm $term, int $positionId, bool $isActive, ?int $ignoreMemberId = null): void
    {
        if (! $isActive) {
            return;
        }

        $positionCode = CatalogItem::query()->whereKey($positionId)->value('code');

        if (! in_array($positionCode, self::UNIQUE_POSITION_CODES, true)) {
            return;
        }

        $exists = $term->members()
            ->where('position_id', $positionId)
            ->where('is_active', true)
            ->when($ignoreMemberId, fn ($query) => $query->whereKeyNot($ignoreMemberId))
            ->exists();

        if ($exists) {
            abort(422, 'Ya existe un miembro activo con este cargo en la directiva.');
        }
    }

    private function syncCondominiumAccess(BoardTerm $term, User $user, int|string|null $roleId, User $approvedBy): void
    {
        if (! $roleId) {
            return;
        }

        $term->condominium->administrators()->syncWithoutDetaching([
            $user->id => [
                'role_id' => $roleId,
                'approved_at' => Carbon::now(),
                'approved_by' => $approvedBy->id,
                'deleted_at' => null,
            ],
        ]);

        if (! $user->role_id) {
            $user->forceFill(['role' => User::ROLE_CONDOMINIUM_ADMIN])->save();
        }
    }
}
