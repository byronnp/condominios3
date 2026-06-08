<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_code_unique');
            $table->foreignId('condominium_id')
                ->nullable()
                ->after('id')
                ->constrained('condominiums')
                ->nullOnDelete();

            $table->unique(['condominium_id', 'code']);
            $table->index(['scope', 'condominium_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['scope', 'condominium_id', 'is_active']);
            $table->dropUnique(['condominium_id', 'code']);
            $table->dropConstrainedForeignId('condominium_id');
            $table->unique('code');
        });
    }
};
