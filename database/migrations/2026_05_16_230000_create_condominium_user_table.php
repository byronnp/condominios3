<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'senior_admin']);

        Schema::create('condominium_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40)->default('condominium_admin');
            $table->boolean('can_manage_houses')->default(true);
            $table->boolean('can_manage_residents')->default(true);
            $table->boolean('can_manage_fees')->default(true);
            $table->boolean('can_manage_payments')->default(true);
            $table->boolean('can_manage_invitations')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['condominium_id', 'user_id']);
            $table->index(['user_id', 'condominium_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condominium_user');
    }
};
