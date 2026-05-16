<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\Auth\UserSession;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'identification_type_id',
    'identification_number',
    'mobile_phone',
    'landline_phone',
    'email',
    'password',
    'role',
    'is_active',
    'last_login_at',
    'last_active_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_SENIOR_ADMIN = 'senior_admin';
    public const ROLE_CONDOMINIUM_ADMIN = 'condominium_admin';
    public const ROLE_RESIDENT = 'resident';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'identification_type_id');
    }

    public function houses(): BelongsToMany
    {
        return $this->belongsToMany(House::class)
            ->withPivot([
                'relationship',
                'can_view_balance',
                'can_view_payments',
                'can_make_payments',
                'can_receive_notifications',
                'can_invite_users',
                'is_primary',
                'approved_at',
                'approved_by',
            ])
            ->withTimestamps();
    }

    public function managedCondominiums(): BelongsToMany
    {
        return $this->belongsToMany(Condominium::class)
            ->withPivot([
                'role',
                'can_manage_houses',
                'can_manage_residents',
                'can_manage_fees',
                'can_manage_payments',
                'can_manage_invitations',
                'approved_at',
                'approved_by',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function isSeniorAdmin(): bool
    {
        return $this->role === self::ROLE_SENIOR_ADMIN;
    }

    public function isCondominiumAdmin(): bool
    {
        return $this->role === self::ROLE_CONDOMINIUM_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->isSeniorAdmin() || $this->isCondominiumAdmin();
    }

    public static function fullName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }
}
