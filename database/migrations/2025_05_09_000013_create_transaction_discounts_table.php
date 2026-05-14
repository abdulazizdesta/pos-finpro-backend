<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                  ->constrained('transactions')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('discount_id')
                  ->nullable()
                  ->constrained('discounts')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
            $table->string('discount_code', 50)->nullable();
            $table->unsignedBigInteger('discount_amount');

            $table->index('transaction_id', 'idx_td_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_discounts');
    }
};
