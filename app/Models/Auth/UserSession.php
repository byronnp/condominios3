<?php

namespace App\Models\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'token_id',
    'ip_address',
    'user_agent',
    'logged_in_at',
    'last_active_at',
    'logged_out_at',
    'revoked_at',
])]
class UserSession extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'last_active_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'revoked_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function active($query): void
    {
        $query->whereNull('revoked_at')->whereNull('logged_out_at');
    }
}
