<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
      public function up(): void
      {
            Schema::create('transaction_items', function (Blueprint $table) {
                  $table->id();
                  $table->foreignId('transaction_id')
                        ->constrained('transactions')
                        ->cascadeOnDelete()
                        ->cascadeOnUpdate();
                  $table->foreignId('product_id')
                        ->constrained('products')
                        ->restrictOnDelete()
                        ->cascadeOnUpdate();
                  $table->foreignId('variant_id')
                        ->nullable()
                        ->constrained('product_variants')
                        ->restrictOnDelete()
                        ->cascadeOnUpdate();
                  $table->string('product_name', 150)
                        ->comment('snapshot nama produk saat transaksi');
                  $table->unsignedBigInteger('unit_price')
                        ->comment('snapshot harga saat transaksi, dalam sen/rupiah terkecil');
                  $table->integer('quantity');
                  $table->unsignedBigInteger('subtotal');

                  $table->index('transaction_id', 'idx_ti_transaction');
                  $table->index('product_id', 'idx_ti_product');
            });
      }

      public function down(): void
      {
            Schema::dropIfExists('transaction_items');
      }
};
