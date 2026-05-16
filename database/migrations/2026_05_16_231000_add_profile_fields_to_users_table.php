<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('identification_type', 30)->nullable()->after('last_name');
            $table->string('identification_number', 30)->nullable()->unique()->after('identification_type');
            $table->string('mobile_phone', 30)->nullable()->after('identification_number');
            $table->string('landline_phone', 30)->nullable()->after('mobile_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'first_name',
                'last_name',
                'identification_type',
                'identification_number',
                'mobile_phone',
                'landline_phone',
            ]);
        });
    }
};
