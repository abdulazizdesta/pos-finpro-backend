<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // business_id nullable karena superadmin tidak punya bisnis
            $table->foreignId('business_id')
                  ->after('id')
                  ->nullable()
                  ->constrained('businesses')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            // Perlebar kolom role dari varchar(10) ke varchar(15)
            // untuk menampung 'superadmin'
            $table->string('role', 15)
                  ->comment('superadmin | owner | admin | kasir')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropColumn('business_id');

            $table->string('role', 10)
                  ->comment('admin | kasir')
                  ->change();
        });
    }
};
