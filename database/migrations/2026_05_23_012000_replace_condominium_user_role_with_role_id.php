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
        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->foreignId('role_id')
                ->nullable()
                ->after('user_id')
                ->constrained('roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        foreach (Role::defaults() as $role) {
            $roleId = DB::table('roles')->where('code', $role['code'])->value('id');

            DB::table('condominium_user')
                ->where('role', $role['code'])
                ->update(['role_id' => $roleId]);
        }

        $condominiumAdminRoleId = DB::table('roles')->where('code', Role::CONDOMINIUM_ADMIN)->value('id');
        DB::table('condominium_user')->whereNull('role_id')->update(['role_id' => $condominiumAdminRoleId]);

        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->string('role', 40)->default(Role::CONDOMINIUM_ADMIN)->after('user_id');
        });

        foreach (Role::defaults() as $role) {
            $roleId = DB::table('roles')->where('code', $role['code'])->value('id');

            DB::table('condominium_user')
                ->where('role_id', $roleId)
                ->update(['role' => $role['code']]);
        }

        Schema::table('condominium_user', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
