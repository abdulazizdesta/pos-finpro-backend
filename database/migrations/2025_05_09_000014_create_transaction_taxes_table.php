<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                  ->constrained('transactions')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('tax_settings_id')
                  ->constrained('tax_settings')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->string('tax_name', 50);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 15, 2);

            $table->index('transaction_id', 'idx_tt_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_taxes');
    }
};
