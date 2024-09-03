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
        Schema::create('purchases_returns_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('purchases_returns_id')->index()->references('id')->on('purchases_returns');
            $table->foreignId('invoice_item_id')->index()->references('id')->on('invoice_items');
            $table->decimal('discount', 21, 8)->default(0);
            $table->decimal('tax', 21, 8)->default(0);
            $table->decimal('price', 21, 8)->default(0);
            $table->decimal('total', 21, 8)->default(0);
            $table->unsignedInteger('qty');
            $table->foreignId('user_id')->index()->references('id')->on('users');
            $table->boolean('transaction_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases_returns_details');
    }
};
