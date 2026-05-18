<?php

use App\Models\Catalog\CatalogItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condominium_payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('catalog_items')->cascadeOnDelete();
            $table->string('display_name')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('instructions')->nullable();
            $table->text('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['condominium_id', 'payment_method_id'], 'condo_payment_method_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('condominium_payment_method_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('condominium_payment_methods')
                ->nullOnDelete();
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->foreignId('condominium_payment_method_id')
                ->nullable()
                ->after('payment_method_id')
                ->constrained('condominium_payment_methods')
                ->nullOnDelete();
        });

        $this->createConfiguredMethodsFromExistingPayments('payments');
        $this->createConfiguredMethodsFromExistingPayments('payment_batches');
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('condominium_payment_method_id');
        });

        Schema::table('payment_batches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('condominium_payment_method_id');
        });

        Schema::dropIfExists('condominium_payment_methods');
    }

    private function createConfiguredMethodsFromExistingPayments(string $table): void
    {
        $rows = DB::table($table)
            ->join('houses', $table.'.house_id', '=', 'houses.id')
            ->whereNotNull($table.'.payment_method_id')
            ->select([
                $table.'.id',
                $table.'.payment_method_id',
                'houses.condominium_id',
            ])
            ->get();

        foreach ($rows as $row) {
            $catalogItem = CatalogItem::query()->find($row->payment_method_id);
            DB::table('condominium_payment_methods')->updateOrInsert([
                'condominium_id' => $row->condominium_id,
                'payment_method_id' => $row->payment_method_id,
            ], [
                'display_name' => $catalogItem?->name,
                'is_enabled' => true,
                'sort_order' => $catalogItem?->sort_order ?? 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]);

            $configuredMethod = DB::table('condominium_payment_methods')
                ->where('condominium_id', $row->condominium_id)
                ->where('payment_method_id', $row->payment_method_id)
                ->first();

            if ($configuredMethod) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['condominium_payment_method_id' => $configuredMethod->id]);
            }
        }
    }
};
