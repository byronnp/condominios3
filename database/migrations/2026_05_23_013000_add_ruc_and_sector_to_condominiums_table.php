<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominiums', function (Blueprint $table): void {
            $table->string('ruc', 20)->nullable()->unique()->after('name');
            $table->string('sector')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('condominiums', function (Blueprint $table): void {
            $table->dropUnique(['ruc']);
            $table->dropColumn(['ruc', 'sector']);
        });
    }
};
