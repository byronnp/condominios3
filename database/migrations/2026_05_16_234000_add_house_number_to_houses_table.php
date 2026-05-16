<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('houses', function (Blueprint $table): void {
            $table->string('house_number', 80)->nullable()->after('code');
            $table->unique(['condominium_id', 'house_number']);
        });
    }

    public function down(): void
    {
        Schema::table('houses', function (Blueprint $table): void {
            $table->dropUnique(['condominium_id', 'house_number']);
            $table->dropColumn('house_number');
        });
    }
};
