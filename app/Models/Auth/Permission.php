<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'code',
    'name',
    'group',
    'scope',
    'description',
    'is_active',
])]
class Permission extends Model
{
    public const SCOPE_SYSTEM = 'system';

    public const SCOPE_CONDOMINIUM = 'condominium';

    public const SCOPE_RESIDENT = 'resident';

    /**
     * @return array<int, array{code: string, name: string, group: string, scope: string, description?: string, is_active: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => 'admin.access', 'name' => 'Acceso administrativo', 'group' => 'access', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'resident.access', 'name' => 'Acceso residente', 'group' => 'access', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'condominium.access', 'name' => 'Acceso operativo a condominio', 'group' => 'access', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'system.manage', 'name' => 'Administrar sistema', 'group' => 'system', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'roles.manage', 'name' => 'Administrar roles', 'group' => 'security', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'menus.manage', 'name' => 'Administrar menus', 'group' => 'security', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'catalogs.manage', 'name' => 'Administrar catalogos', 'group' => 'catalogs', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'condominiums.manage', 'name' => 'Administrar condominios', 'group' => 'condominiums', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'admins.manage', 'name' => 'Administrar usuarios administradores', 'group' => 'security', 'scope' => self::SCOPE_SYSTEM, 'is_active' => true],
            ['code' => 'houses.manage', 'name' => 'Administrar casas', 'group' => 'condominiums', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'residents.manage', 'name' => 'Administrar residentes', 'group' => 'residents', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'fees.manage', 'name' => 'Administrar alicuotas', 'group' => 'billing', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'payments.manage', 'name' => 'Administrar pagos', 'group' => 'billing', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'payment_methods.manage', 'name' => 'Administrar metodos de pago', 'group' => 'billing', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'invitations.manage', 'name' => 'Administrar invitaciones', 'group' => 'residents', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'audit_logs.view', 'name' => 'Ver auditoria', 'group' => 'security', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'board.view', 'name' => 'Ver directivas', 'group' => 'board', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'board.manage', 'name' => 'Administrar directivas', 'group' => 'board', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'fees.view', 'name' => 'Ver alicuotas', 'group' => 'billing', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'payments.view', 'name' => 'Ver pagos', 'group' => 'billing', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'reports.view', 'name' => 'Ver reportes', 'group' => 'reports', 'scope' => self::SCOPE_CONDOMINIUM, 'is_active' => true],
            ['code' => 'resident.balance.view', 'name' => 'Ver estado de cuenta', 'group' => 'resident', 'scope' => self::SCOPE_RESIDENT, 'is_active' => true],
            ['code' => 'resident.payments.view', 'name' => 'Ver pagos de casa', 'group' => 'resident', 'scope' => self::SCOPE_RESIDENT, 'is_active' => true],
            ['code' => 'resident.payments.create', 'name' => 'Registrar pagos de casa', 'group' => 'resident', 'scope' => self::SCOPE_RESIDENT, 'is_active' => true],
            ['code' => 'resident.invitations.create', 'name' => 'Invitar usuarios a casa', 'group' => 'resident', 'scope' => self::SCOPE_RESIDENT, 'is_active' => true],
        ];
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }
}
