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
        Schema::table('house_user', function (Blueprint $table): void {
            $table->foreignId('role_id')
                ->nullable()
                ->after('user_id')
                ->constrained('roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        $residentRoleId = DB::table('roles')->where('code', Role::RESIDENT)->value('id');

        if ($residentRoleId) {
            DB::table('house_user')
                ->whereNull('role_id')
                ->update(['role_id' => $residentRoleId]);
        }

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->foreignId('role_id')
                ->nullable()
                ->after('relationship_type_id')
                ->constrained('roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        if ($residentRoleId) {
            DB::table('house_invitations')
                ->whereNull('role_id')
                ->update(['role_id' => $residentRoleId]);
        }
    }

    public function down(): void
    {
        Schema::table('house_user', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
