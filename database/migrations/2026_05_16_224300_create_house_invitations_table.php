<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('relationship', 40)->default('family');
            $table->uuid('token')->unique();
            $table->boolean('can_view_balance')->default(true);
            $table->boolean('can_view_payments')->default(true);
            $table->boolean('can_make_payments')->default(false);
            $table->boolean('can_receive_notifications')->default(true);
            $table->boolean('can_invite_users')->default(false);
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_invitations');
    }
};
