<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\UserSession;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'role_id',
    'role',
    'is_active',
    'last_login_at',
    'last_active_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_SENIOR_ADMIN = Role::SENIOR_ADMIN;

    public const ROLE_CONDOMINIUM_ADMIN = Role::CONDOMINIUM_ADMIN;

    public const ROLE_RESIDENT = Role::RESIDENT;

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

    public function userRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function identificationType(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'identification_type_id');
    }

    public function houses(): BelongsToMany
    {
        return $this->belongsToMany(House::class)
            ->withPivot([
                'relationship_type_id',
                'role_id',
                'can_receive_notifications',
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
                'role_id',
                'approved_at',
                'approved_by',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function isSeniorAdmin(): bool
    {
        return $this->hasPermission('system.manage');
    }

    public function isCondominiumAdmin(): bool
    {
        return $this->hasPermission('admin.access') && ! $this->hasPermission('system.manage');
    }

    public function isAdmin(): bool
    {
        return $this->hasPermission('admin.access');
    }

    public function hasPermission(string $permission, ?int $condominiumId = null): bool
    {
        if (! $this->role_id) {
            return false;
        }

        if ($permission !== 'system.manage' && $this->roleHasPermission((int) $this->role_id, 'system.manage')) {
            return true;
        }

        $permissionModel = Permission::query()
            ->where('code', $permission)
            ->where('is_active', true)
            ->first();

        if (! $permissionModel) {
            return false;
        }

        if ($permissionModel->scope !== Permission::SCOPE_CONDOMINIUM) {
            if ($permissionModel->scope === Permission::SCOPE_RESIDENT) {
                return false;
            }

            return $this->roleHasPermission((int) $this->role_id, $permission);
        }

        if (! $condominiumId) {
            return false;
        }

        $roleIds = $this->managedCondominiums()
            ->where('condominiums.id', $condominiumId)
            ->wherePivotNotNull('approved_at')
            ->pluck('condominium_user.role_id')
            ->filter()
            ->map(fn ($roleId) => (int) $roleId);

        foreach ($roleIds as $roleId) {
            if ($this->roleHasPermission($roleId, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasHousePermission(string $permission, int $houseId): bool
    {
        if (! $this->role_id) {
            return false;
        }

        if ($permission !== 'system.manage' && $this->roleHasPermission((int) $this->role_id, 'system.manage')) {
            return true;
        }

        $permissionModel = Permission::query()
            ->where('code', $permission)
            ->where('is_active', true)
            ->first();

        if (! $permissionModel) {
            return false;
        }

        if ($permissionModel->scope !== Permission::SCOPE_RESIDENT) {
            return $this->roleHasPermission((int) $this->role_id, $permission);
        }

        $roleIds = $this->houses()
            ->where('houses.id', $houseId)
            ->wherePivotNotNull('approved_at')
            ->pluck('house_user.role_id')
            ->filter()
            ->map(fn ($roleId) => (int) $roleId);

        foreach ($roleIds as $roleId) {
            if ($this->roleHasPermission($roleId, $permission)) {
                return true;
            }
        }

        return false;
    }

    private function roleHasPermission(int $roleId, string $permission): bool
    {
        return Role::query()
            ->whereKey($roleId)
            ->where('is_active', true)
            ->whereHas('permissions', fn (Builder $query) => $query
                ->where('code', $permission)
                ->where('is_active', true))
            ->exists();
    }

    public function scopeWithRole(Builder $query, string $role): Builder
    {
        return $query->whereHas('userRole', fn (Builder $query) => $query->where('code', $role));
    }

    public function getRoleAttribute(?string $value): ?string
    {
        if ($value !== null) {
            return $value;
        }

        if ($this->relationLoaded('userRole')) {
            return $this->userRole?->code;
        }

        if (! $this->role_id) {
            return null;
        }

        return Role::query()->whereKey($this->role_id)->value('code');
    }

    public function setRoleAttribute(string $value): void
    {
        $this->attributes['role_id'] = Role::idForCode($value);
    }

    public static function fullName(string $firstName, string $lastName): string
    {
        return trim($firstName.' '.$lastName);
    }
}
