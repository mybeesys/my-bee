<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //import from sales invoice for sales invoice
        Schema::create('price_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->string('no');

            $table->string('description');

            $table->foreignId('customer_id')->index()->references('id')->on('customers');
            $table->foreignId('user_id')->index()->references('id')->on('users');

            $table->enum('discount_option', ['overall', 'per-item', 'none'])->default('none');
            $table->enum('discount_method', ['amount', 'percent', 'none'])->default('none');
            $table->decimal('discount_amount', 21, 6)->nullable();
            $table->decimal('discount_percent', 21, 6)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_offers');
    }
};
