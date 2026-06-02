<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Catalog\Catalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::defaults() as $role) {
            Role::query()->updateOrCreate([
                'code' => $role['code'],
            ], [
                'name' => $role['name'],
                'scope' => match ($role['code']) {
                    Role::SENIOR_ADMIN => Permission::SCOPE_SYSTEM,
                    Role::RESIDENT => Permission::SCOPE_SYSTEM,
                    default => Permission::SCOPE_CONDOMINIUM,
                },
                'is_system' => true,
                'is_active' => $role['is_active'],
            ]);
        }

        foreach (Permission::defaults() as $permission) {
            Permission::query()->updateOrCreate([
                'code' => $permission['code'],
            ], [
                'name' => $permission['name'],
                'group' => $permission['group'],
                'scope' => $permission['scope'],
                'description' => $permission['description'] ?? null,
                'is_active' => $permission['is_active'],
            ]);
        }

        $this->seedResidentOperationalRoles();
        $this->seedBoardOperationalRoles();
        $this->syncDefaultRolePermissions();
        $this->syncLegacyHouseRoles();

        $identificationTypes = Catalog::query()->updateOrCreate([
            'code' => 'identification_types',
        ], [
            'name' => 'Tipos de identificacion',
            'description' => 'Catalogo global para cedula, RUC y pasaporte.',
            'is_active' => true,
        ]);

        $cedula = null;

        foreach ([
            ['code' => 'cedula', 'name' => 'Cedula', 'sort_order' => 1],
            ['code' => 'ruc', 'name' => 'RUC', 'sort_order' => 2],
            ['code' => 'passport', 'name' => 'Pasaporte', 'sort_order' => 3],
        ] as $item) {
            $catalogItem = $identificationTypes->items()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);

            if ($item['code'] === 'cedula') {
                $cedula = $catalogItem;
            }
        }

        $relationshipTypes = Catalog::query()->updateOrCreate([
            'code' => 'house_relationship_types',
        ], [
            'name' => 'Tipos de relacion con casa',
            'description' => 'Catalogo global para propietarios, familiares, arrendatarios y representantes.',
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'owner', 'name' => 'Propietario', 'sort_order' => 1],
            ['code' => 'spouse', 'name' => 'Conyuge', 'sort_order' => 2],
            ['code' => 'family', 'name' => 'Familiar', 'sort_order' => 3],
            ['code' => 'tenant', 'name' => 'Arrendatario', 'sort_order' => 4],
            ['code' => 'representative', 'name' => 'Representante', 'sort_order' => 5],
        ] as $item) {
            $relationshipTypes->items()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }

        $condominiumStatuses = Catalog::query()->updateOrCreate([
            'code' => 'condominium_statuses',
        ], [
            'name' => 'Estados de condominios',
            'description' => 'Catalogo global para controlar el estado operativo de los condominios.',
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'active', 'name' => 'Activo', 'sort_order' => 1],
            ['code' => 'pending', 'name' => 'Pendiente', 'sort_order' => 2],
            ['code' => 'in_review', 'name' => 'En revision', 'sort_order' => 3],
            ['code' => 'inactive', 'name' => 'Inactivo', 'sort_order' => 4],
        ] as $item) {
            $condominiumStatuses->items()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }

        $boardPositions = Catalog::query()->updateOrCreate([
            'code' => 'board_positions',
        ], [
            'name' => 'Cargos de directiva',
            'description' => 'Catalogo global para cargos de directiva de condominios.',
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'president', 'name' => 'Presidente', 'sort_order' => 1],
            ['code' => 'vice_president', 'name' => 'Vicepresidente', 'sort_order' => 2],
            ['code' => 'treasurer', 'name' => 'Tesorero', 'sort_order' => 3],
            ['code' => 'secretary', 'name' => 'Secretario', 'sort_order' => 4],
            ['code' => 'vocal', 'name' => 'Vocal', 'sort_order' => 5],
        ] as $item) {
            $boardPositions->items()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }

        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@condominios.test'),
        ], [
            'name' => 'BYRON PILATAXI',
            'first_name' => 'BYRON',
            'last_name' => 'PILATAXI',
            'identification_type_id' => $cedula?->id,
            'identification_number' => '1716128911',
            'mobile_phone' => '0992770713',
            'landline_phone' => '3194285',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_SENIOR_ADMIN,
            'is_active' => true,
        ]);

        $this->call([
            MenuSeeder::class,
            SampleDataSeeder::class,
        ]);

        $this->syncDefaultRolePermissions();
    }

    private function syncDefaultRolePermissions(): void
    {
        $permissions = Permission::query()->pluck('id', 'code');

        $seniorAdmin = Role::query()->where('code', Role::SENIOR_ADMIN)->first();
        $condominiumAdmin = Role::query()->where('code', Role::CONDOMINIUM_ADMIN)->first();
        $resident = Role::query()->where('code', Role::RESIDENT)->first();
        $residentOwner = Role::query()->where('code', Role::RESIDENT_OWNER)->first();
        $residentPayer = Role::query()->where('code', Role::RESIDENT_PAYER)->first();
        $residentViewer = Role::query()->where('code', Role::RESIDENT_VIEWER)->first();
        $boardPresident = Role::query()->where('code', Role::BOARD_PRESIDENT)->first();
        $boardTreasurer = Role::query()->where('code', Role::BOARD_TREASURER)->first();
        $boardSecretary = Role::query()->where('code', Role::BOARD_SECRETARY)->first();
        $boardMember = Role::query()->where('code', Role::BOARD_MEMBER)->first();
        $accountant = Role::query()->where('code', Role::ACCOUNTANT)->first();

        $seniorAdmin?->permissions()->sync($permissions->values()->all());

        $condominiumAdmin?->permissions()->sync($permissions->only([
            'admin.access',
            'condominium.access',
            'houses.manage',
            'residents.manage',
            'fees.manage',
            'fees.view',
            'payments.manage',
            'payments.view',
            'payment_methods.manage',
            'invitations.manage',
            'audit_logs.view',
            'board.manage',
            'board.view',
            'reports.view',
        ])->values()->all());

        $resident?->permissions()->sync($permissions->only([
            'resident.access',
        ])->values()->all());

        $residentOwner?->permissions()->sync($permissions->only([
            'resident.balance.view',
            'resident.payments.view',
            'resident.payments.create',
            'resident.invitations.create',
        ])->values()->all());

        $residentPayer?->permissions()->sync($permissions->only([
            'resident.balance.view',
            'resident.payments.view',
            'resident.payments.create',
        ])->values()->all());

        $residentViewer?->permissions()->sync($permissions->only([
            'resident.balance.view',
            'resident.payments.view',
        ])->values()->all());

        $boardPresident?->permissions()->sync($permissions->only([
            'condominium.access',
            'board.manage',
            'board.view',
            'fees.view',
            'payments.view',
            'reports.view',
            'audit_logs.view',
        ])->values()->all());

        $boardTreasurer?->permissions()->sync($permissions->only([
            'condominium.access',
            'board.view',
            'fees.view',
            'payments.view',
            'payments.manage',
            'reports.view',
            'audit_logs.view',
        ])->values()->all());

        $boardSecretary?->permissions()->sync($permissions->only([
            'condominium.access',
            'board.manage',
            'board.view',
            'reports.view',
        ])->values()->all());

        $boardMember?->permissions()->sync($permissions->only([
            'condominium.access',
            'board.view',
        ])->values()->all());

        $accountant?->permissions()->sync($permissions->only([
            'condominium.access',
            'fees.view',
            'payments.view',
            'reports.view',
            'audit_logs.view',
        ])->values()->all());
    }

    private function seedResidentOperationalRoles(): void
    {
        foreach ([
            ['code' => Role::RESIDENT_OWNER, 'name' => 'Residente propietario'],
            ['code' => Role::RESIDENT_PAYER, 'name' => 'Residente con pagos'],
            ['code' => Role::RESIDENT_VIEWER, 'name' => 'Residente lector'],
        ] as $role) {
            Role::query()->updateOrCreate([
                'code' => $role['code'],
            ], [
                'name' => $role['name'],
                'scope' => Permission::SCOPE_RESIDENT,
                'is_system' => false,
                'is_active' => true,
            ]);
        }
    }

    private function seedBoardOperationalRoles(): void
    {
        foreach ([
            ['code' => Role::BOARD_PRESIDENT, 'name' => 'Presidente de directiva'],
            ['code' => Role::BOARD_TREASURER, 'name' => 'Tesorero de directiva'],
            ['code' => Role::BOARD_SECRETARY, 'name' => 'Secretario de directiva'],
            ['code' => Role::BOARD_MEMBER, 'name' => 'Miembro de directiva'],
            ['code' => Role::ACCOUNTANT, 'name' => 'Contador externo'],
        ] as $role) {
            Role::query()->updateOrCreate([
                'code' => $role['code'],
            ], [
                'name' => $role['name'],
                'scope' => Permission::SCOPE_CONDOMINIUM,
                'is_system' => false,
                'is_active' => true,
            ]);
        }
    }

    private function syncLegacyHouseRoles(): void
    {
        if (! Schema::hasColumn('house_user', 'can_make_payments')) {
            return;
        }

        $ownerRoleId = Role::idForCode(Role::RESIDENT_OWNER);
        $payerRoleId = Role::idForCode(Role::RESIDENT_PAYER);
        $viewerRoleId = Role::idForCode(Role::RESIDENT_VIEWER);

        if ($ownerRoleId) {
            DB::table('house_user')
                ->where(fn ($query) => $query
                    ->where('can_invite_users', true)
                    ->orWhere('is_primary', true))
                ->update(['role_id' => $ownerRoleId]);
        }

        if ($payerRoleId) {
            DB::table('house_user')
                ->where('can_make_payments', true)
                ->where('can_invite_users', false)
                ->where('is_primary', false)
                ->update(['role_id' => $payerRoleId]);
        }

        if ($viewerRoleId) {
            DB::table('house_user')
                ->where('can_make_payments', false)
                ->where('can_invite_users', false)
                ->where('is_primary', false)
                ->update(['role_id' => $viewerRoleId]);

            DB::table('house_invitations')
                ->whereNull('accepted_at')
                ->update(['role_id' => $viewerRoleId]);
        }
    }
}
