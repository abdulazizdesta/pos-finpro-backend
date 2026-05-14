<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('outlet_id')
                ->constrained('outlets')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('closing_cash')->nullable();
            $table->string('status', 10)
                ->default('open')
                ->comment('open | closed');

            $table->index(['outlet_id', 'status'], 'idx_shift_outlet_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
