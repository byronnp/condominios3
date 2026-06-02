<?php

namespace App\Models\Board;

use App\Models\Auth\Role;
use App\Models\Catalog\CatalogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['board_term_id', 'user_id', 'position_id', 'role_id', 'starts_at', 'ends_at', 'is_active', 'notes'])]
class BoardMember extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function boardTerm(): BelongsTo
    {
        return $this->belongsTo(BoardTerm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'position_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
