<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
    'description',
    'scope',
    'is_system',
    'is_active',
])]
class Role extends Model
{
    public const SENIOR_ADMIN = 'senior_admin';

    public const CONDOMINIUM_ADMIN = 'condominium_admin';

    public const RESIDENT = 'resident';

    public const RESIDENT_OWNER = 'resident_owner';

    public const RESIDENT_PAYER = 'resident_payer';

    public const RESIDENT_VIEWER = 'resident_viewer';

    /**
     * @return array<int, array{code: string, name: string, is_active: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => self::SENIOR_ADMIN, 'name' => 'Administrador senior', 'is_active' => true],
            ['code' => self::CONDOMINIUM_ADMIN, 'name' => 'Administrador de condominio', 'is_active' => true],
            ['code' => self::RESIDENT, 'name' => 'Residente', 'is_active' => true],
        ];
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public static function idForCode(string $code): ?int
    {
        return static::query()->where('code', $code)->value('id');
    }
}
