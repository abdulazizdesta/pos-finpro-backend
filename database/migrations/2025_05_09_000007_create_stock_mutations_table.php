<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')
                  ->constrained('stocks')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('type', 15)
                  ->comment('sale | restock | adjustment | refund');
            $table->integer('quantity_change')
                  ->comment('negatif = keluar, positif = masuk');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->unsignedBigInteger('reference_id')
                  ->nullable()
                  ->comment('transaction_id jika type=sale');
            $table->string('reference_type', 20)
                  ->nullable()
                  ->comment('transaction | manual');
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['stock_id', 'created_at'], 'idx_mutation_stock_date');
            $table->index('type', 'idx_mutation_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
