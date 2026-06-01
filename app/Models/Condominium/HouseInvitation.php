<?php

namespace App\Models\Condominium;

use App\Models\Catalog\CatalogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'house_id',
    'email',
    'relationship_type_id',
    'role_id',
    'token',
    'can_receive_notifications',
    'invited_by',
    'accepted_by',
    'accepted_at',
    'expires_at',
    'revoked_at',
])]
class HouseInvitation extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'can_receive_notifications' => 'boolean',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function relationshipType(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'relationship_type_id');
    }
}
