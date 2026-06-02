<?php

namespace App\Services\Board;

use App\Models\Board\BoardTerm;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BoardTermService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{name:string, starts_at:string, ends_at:string, status?:string, notes?:string|null}  $data
     */
    public function create(Condominium $condominium, array $data, User $user, ?Request $request = null): BoardTerm
    {
        return DB::transaction(function () use ($condominium, $data, $user, $request): BoardTerm {
            $data['status'] ??= BoardTerm::STATUS_DRAFT;

            $this->abortIfInvalidDateRange($data['starts_at'], $data['ends_at']);
            $this->abortIfActiveTermOverlaps($condominium->id, $data['starts_at'], $data['ends_at'], $data['status']);

            $term = $condominium->boardTerms()->create($data);

            $this->audit->record(
                action: 'board_term.created',
                module: 'board',
                condominiumId: $condominium->id,
                user: $user,
                entity: $term,
                description: 'Gestion de directiva creada.',
                newValues: $term->toArray(),
                request: $request,
            );

            return $term->load(['condominium', 'members.user', 'members.position', 'members.role']);
        });
    }

    /**
     * @param  array{name?:string, starts_at?:string, ends_at?:string, status?:string, notes?:string|null}  $data
     */
    public function update(BoardTerm $term, array $data, User $user, ?Request $request = null): BoardTerm
    {
        return DB::transaction(function () use ($term, $data, $user, $request): BoardTerm {
            $oldValues = $term->toArray();

            $startsAt = $data['starts_at'] ?? $term->starts_at->toDateString();
            $endsAt = $data['ends_at'] ?? $term->ends_at->toDateString();
            $status = $data['status'] ?? $term->status;

            $this->abortIfInvalidDateRange($startsAt, $endsAt);
            $this->abortIfActiveTermOverlaps($term->condominium_id, $startsAt, $endsAt, $status, $term->id);

            $term->update($data);

            $this->audit->record(
                action: 'board_term.updated',
                module: 'board',
                condominiumId: $term->condominium_id,
                user: $user,
                entity: $term,
                description: 'Gestion de directiva actualizada.',
                oldValues: $oldValues,
                newValues: $term->fresh()->toArray(),
                request: $request,
            );

            return $term->load(['condominium', 'members.user', 'members.position', 'members.role']);
        });
    }

    private function abortIfActiveTermOverlaps(
        int $condominiumId,
        string $startsAt,
        string $endsAt,
        string $status,
        ?int $ignoreTermId = null,
    ): void {
        if ($status !== BoardTerm::STATUS_ACTIVE) {
            return;
        }

        $exists = BoardTerm::query()
            ->where('condominium_id', $condominiumId)
            ->where('status', BoardTerm::STATUS_ACTIVE)
            ->when($ignoreTermId, fn ($query) => $query->whereKeyNot($ignoreTermId))
            ->whereDate('starts_at', '<=', $endsAt)
            ->whereDate('ends_at', '>=', $startsAt)
            ->exists();

        if ($exists) {
            abort(422, 'Ya existe una directiva activa para este condominio en ese rango de fechas.');
        }
    }

    private function abortIfInvalidDateRange(string $startsAt, string $endsAt): void
    {
        if (Carbon::parse($endsAt)->lt(Carbon::parse($startsAt))) {
            abort(422, 'La fecha de fin de la directiva no puede ser menor a la fecha de inicio.');
        }
    }
}
