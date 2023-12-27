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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('product_id')->index()->references('id')->on('products');

            $table->text('variant_library_options_ids'); //combinations

            $table->string('sku')->unique()->index();

            $table->foreignId('warehouse_id')->index()->references('id')->on('warehouses')->restrictOnDelete();

            $table->string('name_ar'); //build name from the options
            $table->string('name_en'); //build name from the options

            $table->boolean('unlimited_qty')->default(0);

            $table->integer('qty')->nullable();
            $table->integer('qty_out')->default(0);

            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('price', 19, 4)->nullable();
            $table->decimal('discount_price', 19, 4)->nullable();

            $table->integer('weight_kg')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
