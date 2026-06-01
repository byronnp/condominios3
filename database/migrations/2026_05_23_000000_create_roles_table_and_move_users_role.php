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
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (Role::defaults() as $role) {
            DB::table('roles')->updateOrInsert(
                ['code' => $role['code']],
                [
                    'name' => $role['name'],
                    'is_active' => $role['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('role_id')
                ->nullable()
                ->after('password')
                ->constrained('roles')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->join('roles', 'users.role', '=', 'roles.code')
                ->update(['users.role_id' => DB::raw('roles.id')]);
        }

        $residentRoleId = DB::table('roles')->where('code', Role::RESIDENT)->value('id');
        DB::table('users')->whereNull('role_id')->update(['role_id' => $residentRoleId]);

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('role');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 30)->default(Role::RESIDENT)->after('password')->index();
        });

        DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->update(['users.role' => DB::raw('roles.code')]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
