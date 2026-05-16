<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 40)->default('owner');
            $table->boolean('can_view_balance')->default(true);
            $table->boolean('can_view_payments')->default(true);
            $table->boolean('can_make_payments')->default(false);
            $table->boolean('can_receive_notifications')->default(true);
            $table->boolean('can_invite_users')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['house_id', 'user_id']);
            $table->index(['user_id', 'house_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_user');
    }
};
