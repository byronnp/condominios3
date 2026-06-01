<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condominiums', function (Blueprint $table): void {
            $table->unsignedInteger('total_houses')->default(0)->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('condominiums', function (Blueprint $table): void {
            $table->dropColumn('total_houses');
        });
    }
};
