<?php

use App\Models\Auth\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureResidentOperationalRoles();
        $this->syncLegacyRoles();

        Schema::table('house_user', function (Blueprint $table): void {
            $table->dropColumn([
                'can_view_balance',
                'can_view_payments',
                'can_make_payments',
                'can_invite_users',
            ]);
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->dropColumn([
                'can_view_balance',
                'can_view_payments',
                'can_make_payments',
                'can_invite_users',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('house_user', function (Blueprint $table): void {
            $table->boolean('can_view_balance')->default(true)->after('relationship_type_id');
            $table->boolean('can_view_payments')->default(true)->after('can_view_balance');
            $table->boolean('can_make_payments')->default(false)->after('can_view_payments');
            $table->boolean('can_invite_users')->default(false)->after('can_receive_notifications');
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->boolean('can_view_balance')->default(true)->after('token');
            $table->boolean('can_view_payments')->default(true)->after('can_view_balance');
            $table->boolean('can_make_payments')->default(false)->after('can_view_payments');
            $table->boolean('can_invite_users')->default(false)->after('can_receive_notifications');
        });
    }

    private function ensureResidentOperationalRoles(): void
    {
        foreach ([
            Role::RESIDENT_OWNER => 'Residente propietario',
            Role::RESIDENT_PAYER => 'Residente con pagos',
            Role::RESIDENT_VIEWER => 'Residente lector',
        ] as $code => $name) {
            DB::table('roles')->updateOrInsert([
                'code' => $code,
            ], [
                'name' => $name,
                'scope' => 'resident',
                'is_system' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function syncLegacyRoles(): void
    {
        $ownerRoleId = DB::table('roles')->where('code', Role::RESIDENT_OWNER)->value('id');
        $payerRoleId = DB::table('roles')->where('code', Role::RESIDENT_PAYER)->value('id');
        $viewerRoleId = DB::table('roles')->where('code', Role::RESIDENT_VIEWER)->value('id');

        DB::table('house_user')
            ->where(fn ($query) => $query
                ->where('can_invite_users', true)
                ->orWhere('is_primary', true))
            ->update(['role_id' => $ownerRoleId]);

        DB::table('house_user')
            ->where('can_make_payments', true)
            ->where('can_invite_users', false)
            ->where('is_primary', false)
            ->update(['role_id' => $payerRoleId]);

        DB::table('house_user')
            ->where('can_make_payments', false)
            ->where('can_invite_users', false)
            ->where('is_primary', false)
            ->update(['role_id' => $viewerRoleId]);

        DB::table('house_invitations')->update(['role_id' => $viewerRoleId]);
    }
};
