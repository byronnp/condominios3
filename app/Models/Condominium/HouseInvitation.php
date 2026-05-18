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
    'token',
    'can_view_balance',
    'can_view_payments',
    'can_make_payments',
    'can_receive_notifications',
    'can_invite_users',
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
            'can_view_balance' => 'boolean',
            'can_view_payments' => 'boolean',
            'can_make_payments' => 'boolean',
            'can_receive_notifications' => 'boolean',
            'can_invite_users' => 'boolean',
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
