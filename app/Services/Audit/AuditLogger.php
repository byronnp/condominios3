<?php

namespace App\Services\Audit;

use App\Models\Audit\AuditLog;
use App\Models\Billing\FeeCharge;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $action,
        string $module,
        ?int $condominiumId = null,
        ?User $user = null,
        ?Model $entity = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Request $request = null,
        string $source = 'api',
    ): AuditLog {
        return AuditLog::query()->create([
            'condominium_id' => $condominiumId,
            'user_id' => $user?->id,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'old_values' => $this->clean($oldValues),
            'new_values' => $this->clean($newValues),
            'metadata' => $this->clean($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'source' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $errors
     */
    public function recordError(
        Throwable $exception,
        Request $request,
        string $action,
        int $status,
        ?array $errors = null,
    ): ?AuditLog {
        try {
            return $this->record(
                action: $action,
                module: 'errors',
                condominiumId: $this->resolveCondominiumId($request),
                user: $request->user(),
                description: 'Error API '.$status.' en '.$request->method().' '.$request->path().'.',
                metadata: [
                    'status_code' => $status,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'route_name' => $request->route()?->getName(),
                    'route_action' => $request->route()?->getActionName(),
                    'route_parameters' => $this->routeParameters($request),
                    'query' => $request->query(),
                    'input' => $request->except(['password', 'password_confirmation', 'token']),
                    'errors' => $errors,
                    'file' => $status >= 500 ? $exception->getFile() : null,
                    'line' => $status >= 500 ? $exception->getLine() : null,
                    'trace' => $status >= 500 ? $this->shortTrace($exception) : null,
                ],
                request: $request,
                source: 'api_error',
            );
        } catch (Throwable $auditException) {
            Log::warning('Unable to write audit error log: '.$auditException->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function clean(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (array_keys($values) as $key) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, ['password', 'secret', 'token', 'api_key', 'secret_key', 'webhook_secret', 'private_key', 'public_key', 'config'], true)
                || str_ends_with($normalizedKey, '_secret')
                || str_ends_with($normalizedKey, '_token')
            ) {
                $values[$key] = '[redacted]';

                continue;
            }

            if (is_array($values[$key])) {
                $values[$key] = $this->clean($values[$key]);
            }
        }

        return $values;
    }

    private function resolveCondominiumId(Request $request): ?int
    {
        $condominium = $request->route('condominium');

        if ($condominium instanceof Condominium) {
            return $condominium->id;
        }

        if (is_numeric($condominium)) {
            return (int) $condominium;
        }

        $house = $request->route('house');

        if ($house instanceof House) {
            return $house->condominium_id;
        }

        $houseId = $request->input('house_id') ?? (is_numeric($house) ? $house : null);

        if ($houseId) {
            return House::query()->whereKey($houseId)->value('condominium_id');
        }

        $feeChargeId = $request->input('fee_charge_id');

        if ($feeChargeId) {
            return FeeCharge::query()
                ->join('houses', 'fee_charges.house_id', '=', 'houses.id')
                ->where('fee_charges.id', $feeChargeId)
                ->value('houses.condominium_id');
        }

        $inputCondominiumId = $request->input('condominium_id');

        return is_numeric($inputCondominiumId) ? (int) $inputCondominiumId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn ($value) => $value instanceof Model ? [
                'type' => class_basename($value),
                'id' => $value->getKey(),
            ] : $value)
            ->all();
    }

    /**
     * @return list<array{file:string|null,line:int|null,function:string|null,class:string|null}>
     */
    private function shortTrace(Throwable $exception): array
    {
        return collect($exception->getTrace())
            ->take(8)
            ->map(fn (array $frame) => [
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ])
            ->values()
            ->all();
    }
}
