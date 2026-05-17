<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->unsignedBigInteger('variant_id')
                  ->default(0)
                  ->comment('sentinel: 0 = no variant, no FK intentionally');
            $table->foreignId('outlet_id')
                  ->constrained('outlets')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->integer('quantity')->default(0);
            $table->integer('min_threshold')
                  ->default(5)
                  ->comment('trigger alert restock');
            $table->timestamp('updated_at')
                  ->nullable()
                  ->useCurrentOnUpdate();

            $table->unique(['product_id', 'variant_id', 'outlet_id'], 'unique_stock');
            $table->index(['product_id', 'outlet_id'], 'idx_stock_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
