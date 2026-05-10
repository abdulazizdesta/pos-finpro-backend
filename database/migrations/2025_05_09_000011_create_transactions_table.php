<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code', 25)
                  ->unique()
                  ->comment('format: TRX-YYYYMMDD-XXXX');
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('outlet_id')
                  ->constrained('outlets')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('shift_id')
                  ->constrained('shifts')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('payment_method', 10)
                  ->comment('cash | qris | card');
            $table->string('payment_status', 10)
                  ->default('paid')
                  ->comment('paid | pending | refunded');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['outlet_id', 'created_at'], 'idx_trx_outlet_date');
            $table->index('shift_id', 'idx_trx_shift');
            $table->index('payment_method', 'idx_trx_payment_method');
            $table->index('payment_status', 'idx_trx_status');
            $table->index('created_at', 'idx_trx_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
