<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('identification_type_id')
                ->nullable()
                ->after('last_name')
                ->constrained('catalog_items')
                ->nullOnDelete();
        });

        DB::table('users')
            ->join('catalog_items', 'catalog_items.code', '=', 'users.identification_type')
            ->join('catalogs', 'catalogs.id', '=', 'catalog_items.catalog_id')
            ->where('catalogs.code', 'identification_types')
            ->whereNotNull('users.identification_type')
            ->update(['users.identification_type_id' => DB::raw('catalog_items.id')]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('identification_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('identification_type', 30)->nullable()->after('last_name');
        });

        DB::table('users')
            ->join('catalog_items', 'catalog_items.id', '=', 'users.identification_type_id')
            ->update(['users.identification_type' => DB::raw('catalog_items.code')]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['identification_type_id']);
            $table->dropColumn('identification_type_id');
        });
    }
};
