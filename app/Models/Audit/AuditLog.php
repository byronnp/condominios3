<?php

namespace App\Models\Audit;

use App\Models\Condominium\Condominium;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'condominium_id',
    'user_id',
    'action',
    'module',
    'entity_type',
    'entity_id',
    'description',
    'old_values',
    'new_values',
    'metadata',
    'ip_address',
    'user_agent',
    'source',
])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
