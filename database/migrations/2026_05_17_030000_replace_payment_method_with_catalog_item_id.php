<?php

use App\Models\Catalog\Catalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('paid_at')
                ->constrained('catalog_items')
                ->nullOnDelete();
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('paid_at')
                ->constrained('catalog_items')
                ->nullOnDelete();
        });

        $this->migratePaymentMethodsToCatalogIds('payments');
        $this->migratePaymentMethodsToCatalogIds('payment_batches');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->dropColumn('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('payment_method', 40)->nullable()->after('paid_at');
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->string('payment_method', 40)->nullable()->after('paid_at');
        });

        $this->migratePaymentMethodIdsToCodes('payments');
        $this->migratePaymentMethodIdsToCodes('payment_batches');

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }

    private function migratePaymentMethodsToCatalogIds(string $table): void
    {
        $paymentMethods = Catalog::query()
            ->where('code', 'payment_methods')
            ->first()
            ?->items()
            ->pluck('id', 'code') ?? collect();

        foreach ($paymentMethods as $code => $id) {
            DB::table($table)
                ->where('payment_method', $code)
                ->update(['payment_method_id' => $id]);
        }
    }

    private function migratePaymentMethodIdsToCodes(string $table): void
    {
        DB::table($table)
            ->join('catalog_items', $table.'.payment_method_id', '=', 'catalog_items.id')
            ->update([$table.'.payment_method' => DB::raw('catalog_items.code')]);
    }
};
