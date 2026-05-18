<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $catalogId = DB::table('catalogs')->insertOrIgnore([
            'code' => 'house_relationship_types',
            'name' => 'Tipos de relacion con casa',
            'description' => 'Catalogo global para propietarios, familiares, arrendatarios y representantes.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogId = DB::table('catalogs')->where('code', 'house_relationship_types')->value('id');

        foreach ([
            ['code' => 'owner', 'name' => 'Propietario', 'sort_order' => 1],
            ['code' => 'spouse', 'name' => 'Conyuge', 'sort_order' => 2],
            ['code' => 'family', 'name' => 'Familiar', 'sort_order' => 3],
            ['code' => 'tenant', 'name' => 'Arrendatario', 'sort_order' => 4],
            ['code' => 'representative', 'name' => 'Representante', 'sort_order' => 5],
        ] as $item) {
            DB::table('catalog_items')->updateOrInsert([
                'catalog_id' => $catalogId,
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }

        Schema::table('house_user', function (Blueprint $table): void {
            $table->foreignId('relationship_type_id')
                ->nullable()
                ->after('user_id')
                ->constrained('catalog_items')
                ->nullOnDelete();
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->foreignId('relationship_type_id')
                ->nullable()
                ->after('email')
                ->constrained('catalog_items')
                ->nullOnDelete();
        });

        DB::table('house_user')
            ->join('catalog_items', 'catalog_items.code', '=', 'house_user.relationship')
            ->where('catalog_items.catalog_id', $catalogId)
            ->update(['house_user.relationship_type_id' => DB::raw('catalog_items.id')]);

        DB::table('house_invitations')
            ->join('catalog_items', 'catalog_items.code', '=', 'house_invitations.relationship')
            ->where('catalog_items.catalog_id', $catalogId)
            ->update(['house_invitations.relationship_type_id' => DB::raw('catalog_items.id')]);

        Schema::table('house_user', function (Blueprint $table): void {
            $table->dropColumn('relationship');
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->dropColumn('relationship');
        });
    }

    public function down(): void
    {
        Schema::table('house_user', function (Blueprint $table): void {
            $table->string('relationship', 40)->nullable()->after('user_id');
        });

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->string('relationship', 40)->nullable()->after('email');
        });

        DB::table('house_user')
            ->join('catalog_items', 'catalog_items.id', '=', 'house_user.relationship_type_id')
            ->update(['house_user.relationship' => DB::raw('catalog_items.code')]);

        DB::table('house_invitations')
            ->join('catalog_items', 'catalog_items.id', '=', 'house_invitations.relationship_type_id')
            ->update(['house_invitations.relationship' => DB::raw('catalog_items.code')]);

        Schema::table('house_invitations', function (Blueprint $table): void {
            $table->dropForeign(['relationship_type_id']);
            $table->dropColumn('relationship_type_id');
        });

        Schema::table('house_user', function (Blueprint $table): void {
            $table->dropForeign(['relationship_type_id']);
            $table->dropColumn('relationship_type_id');
        });
    }
};
