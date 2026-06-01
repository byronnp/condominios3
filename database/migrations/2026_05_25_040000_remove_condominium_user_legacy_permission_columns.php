<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncLegacyRoles();

        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->dropColumn([
                'can_manage_houses',
                'can_manage_residents',
                'can_manage_fees',
                'can_manage_payments',
                'can_manage_invitations',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->boolean('can_manage_houses')->default(true)->after('role_id');
            $table->boolean('can_manage_residents')->default(true)->after('can_manage_houses');
            $table->boolean('can_manage_fees')->default(true)->after('can_manage_residents');
            $table->boolean('can_manage_payments')->default(true)->after('can_manage_fees');
            $table->boolean('can_manage_invitations')->default(true)->after('can_manage_payments');
        });
    }

    private function syncLegacyRoles(): void
    {
        $rows = DB::table('condominium_user')
            ->select([
                'can_manage_houses',
                'can_manage_residents',
                'can_manage_fees',
                'can_manage_payments',
                'can_manage_invitations',
            ])
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $permissions = $this->permissionCodesFor($row);

            if (count($permissions) === 7) {
                continue;
            }

            $code = 'condominium_legacy_'.($row->can_manage_houses ? '1' : '0')
                .($row->can_manage_residents ? '1' : '0')
                .($row->can_manage_fees ? '1' : '0')
                .($row->can_manage_payments ? '1' : '0')
                .($row->can_manage_invitations ? '1' : '0');

            DB::table('roles')->updateOrInsert([
                'code' => $code,
            ], [
                'name' => 'Rol migrado '.$code,
                'scope' => 'condominium',
                'is_system' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleId = DB::table('roles')->where('code', $code)->value('id');
            $permissionIds = DB::table('permissions')
                ->whereIn('code', $permissions)
                ->pluck('id');

            DB::table('role_permissions')->where('role_id', $roleId)->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('condominium_user')
                ->where('can_manage_houses', (bool) $row->can_manage_houses)
                ->where('can_manage_residents', (bool) $row->can_manage_residents)
                ->where('can_manage_fees', (bool) $row->can_manage_fees)
                ->where('can_manage_payments', (bool) $row->can_manage_payments)
                ->where('can_manage_invitations', (bool) $row->can_manage_invitations)
                ->update(['role_id' => $roleId]);
        }
    }

    /**
     * @return list<string>
     */
    private function permissionCodesFor(object $row): array
    {
        $permissions = ['admin.access'];

        if ($row->can_manage_houses) {
            $permissions[] = 'houses.manage';
        }

        if ($row->can_manage_residents) {
            $permissions[] = 'residents.manage';
        }

        if ($row->can_manage_fees) {
            $permissions[] = 'fees.manage';
        }

        if ($row->can_manage_payments) {
            $permissions[] = 'payments.manage';
            $permissions[] = 'payment_methods.manage';
        }

        if ($row->can_manage_invitations) {
            $permissions[] = 'invitations.manage';
        }

        return array_values(array_unique($permissions));
    }
};
