<?php

namespace App\Transformers;

use App\Models\Audit\AuditLog;

class AuditLogTransformer
{
    public static function transform(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'condominium_id' => $log->relationLoaded('condominium') && $log->condominium ? [
                'id' => $log->condominium->id,
                'name' => $log->condominium->name,
            ] : null,
            'user' => $log->relationLoaded('user') && $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
            ] : null,
            'action' => $log->action,
            'module' => $log->module,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'description' => $log->description,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'source' => $log->source,
            'created_at' => $log->created_at,
        ];
    }
}
