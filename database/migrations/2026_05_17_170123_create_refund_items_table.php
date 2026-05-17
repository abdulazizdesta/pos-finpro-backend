<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')
                ->constrained('refunds')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('transaction_item_id')
                ->constrained('transaction_items')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_items');
    }
};
