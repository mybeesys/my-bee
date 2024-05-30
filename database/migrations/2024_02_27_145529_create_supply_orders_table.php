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
        Schema::create('supply_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->string('no');

            $table->string('description');

            $table->foreignId('supplier_id')->index()->references('id')->on('suppliers');
            $table->foreignId('user_id')->index()->references('id')->on('users');

            $table->unique(['tenant_id', 'no']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supply_orders');
    }
};
