<?php

namespace App\Models\Auth;

use App\Models\Condominium\Condominium;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

#[Fillable([
    'condominium_id',
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

    public const BOARD_PRESIDENT = 'board_president';

    public const BOARD_TREASURER = 'board_treasurer';

    public const BOARD_SECRETARY = 'board_secretary';

    public const BOARD_MEMBER = 'board_member';

    public const ACCOUNTANT = 'accountant';

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
            'condominium_id' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public static function idForCode(string $code, ?int $condominiumId = null): ?int
    {
        $query = static::query()->where('code', $code);

        if (! Schema::hasColumn('roles', 'condominium_id')) {
            return $query->value('id');
        }

        if ($condominiumId) {
            $query
                ->where(fn ($query) => $query
                    ->where('condominium_id', $condominiumId)
                    ->orWhereNull('condominium_id'))
                ->orderByRaw('case when condominium_id = ? then 0 else 1 end', [$condominiumId]);
        } else {
            $query->whereNull('condominium_id');
        }

        return $query->value('id');
    }
}
