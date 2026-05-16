<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('house_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['house_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_charges');
    }
};
