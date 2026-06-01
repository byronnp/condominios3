<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->foreignId('required_permission_id')
                ->nullable()
                ->after('is_active')
                ->constrained('permissions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        DB::table('menus')
            ->join('permissions', 'menus.required_permission', '=', 'permissions.code')
            ->update(['menus.required_permission_id' => DB::raw('permissions.id')]);

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropIndex(['required_permission']);
            $table->dropColumn('required_permission');
        });
    }

    public function down(): void
    {
        Schema::table('menus', function (Blueprint $table): void {
            $table->string('required_permission', 80)->nullable()->index()->after('is_active');
        });

        DB::table('menus')
            ->leftJoin('permissions', 'menus.required_permission_id', '=', 'permissions.id')
            ->update(['menus.required_permission' => DB::raw('permissions.code')]);

        Schema::table('menus', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('required_permission_id');
        });
    }
};
