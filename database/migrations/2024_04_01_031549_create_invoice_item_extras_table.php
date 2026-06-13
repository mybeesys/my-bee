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
        Schema::create('invoice_item_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('invoice_item_id')->index()->references('id')->on('invoice_items');
            $table->foreignId('product_extra_id')->index()->references('id')->on('product_extra');

            $table->string('display_name');

            //multiple extras not supported at this moment
            $table->integer('qty')->nullable()->default(1);
            $table->decimal('unit_price', 21, 6);

            $table->boolean('cancelled')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_extras');
    }
};
