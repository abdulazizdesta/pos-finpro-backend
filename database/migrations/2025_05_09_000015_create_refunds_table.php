<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                  ->constrained('transactions')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->decimal('amount', 15, 2);
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')
                  ->constrained('users')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
