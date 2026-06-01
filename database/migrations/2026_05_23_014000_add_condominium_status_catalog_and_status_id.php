<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $catalogId = DB::table('catalogs')->updateOrInsert([
            'code' => 'condominium_statuses',
        ], [
            'name' => 'Estados de condominios',
            'description' => 'Catalogo global para controlar el estado operativo de los condominios.',
            'is_active' => true,
            'updated_at' => $now,
            'created_at' => $now,
        ]);

        $catalogId = DB::table('catalogs')->where('code', 'condominium_statuses')->value('id');

        foreach ([
            ['code' => 'active', 'name' => 'Activo', 'sort_order' => 1],
            ['code' => 'pending', 'name' => 'Pendiente', 'sort_order' => 2],
            ['code' => 'in_review', 'name' => 'En revision', 'sort_order' => 3],
            ['code' => 'inactive', 'name' => 'Inactivo', 'sort_order' => 4],
        ] as $item) {
            DB::table('catalog_items')->updateOrInsert([
                'catalog_id' => $catalogId,
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
        }

        if (! Schema::hasColumn('condominiums', 'status_id')) {
            Schema::table('condominiums', function (Blueprint $table): void {
                $table->foreignId('status_id')->nullable()->after('sector')->constrained('catalog_items')->nullOnDelete();
            });
        }

        $activeStatusId = DB::table('catalog_items')
            ->where('catalog_id', $catalogId)
            ->where('code', 'active')
            ->value('id');

        $inactiveStatusId = DB::table('catalog_items')
            ->where('catalog_id', $catalogId)
            ->where('code', 'inactive')
            ->value('id');

        DB::table('condominiums')
            ->where('is_active', true)
            ->update(['status_id' => $activeStatusId]);

        DB::table('condominiums')
            ->where('is_active', false)
            ->update(['status_id' => $inactiveStatusId]);

    }

    public function down(): void
    {
        Schema::table('condominiums', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_id');
        });

        $catalogId = DB::table('catalogs')->where('code', 'condominium_statuses')->value('id');

        if ($catalogId) {
            DB::table('catalog_items')->where('catalog_id', $catalogId)->delete();
            DB::table('catalogs')->where('id', $catalogId)->delete();
        }
    }
};
