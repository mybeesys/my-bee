<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('name');
            $table->enum('type', ['basic', 'variants']);
            $table->string('barcode')->index()->nullable();
            $table->string('sku')->unique()->index();
            $table->integer('calories')->nullable();
//            $table->foreignId('warehouse_id')->nullable()->index()->references('id')->on('warehouses');
            $table->foreignId('category_id')->index()->references('id')->on('categories');

//            $table->decimal('unit_cost', 19, 4)->nullable();
//            $table->decimal('price', 21, 6)->default(0);
//            $table->decimal('discount_price', 21, 6)->nullable();
            $table->integer('negative_stock')->default(0);

            $table->integer('security_stock')->default(10);

            $table->text('description')->nullable();
            $table->text('attributes')->nullable();

            $table->boolean('free_delivery')->default(0);

            $table->foreignId('tax_profile_id')->nullable()->references('id')->on('tax_profiles');

            $table->integer('min_orders')->nullable();
            $table->integer('max_orders')->nullable();

            $table->boolean('published')->default(1);

            $table->integer('sort')->nullable();

            $table->unique(['name', 'tenant_id']);
            $table->unique(['barcode', 'tenant_id']);

            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('product_id')->index()->references('id')->on('products');

            $table->text('variant_library_options_ids'); //combinations

            $table->string('sku')->unique()->index();

            $table->string('name_ar'); //build name from the options
            $table->string('name_en'); //build name from the options

            $table->integer('negative_stock')->default(0);

            $table->integer('weight_kg')->nullable();

            $table->timestamps();
        });

        Schema::create('product_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('product_id')->index()->references('id')->on('products');
            $table->foreignId('item_extra_id')->index()->references('id')->on('item_extras');

            $table->string('sku')->unique()->index()->nullable();

//            $table->decimal('unit_cost', 21, 6)->nullable();
//            $table->decimal('price', 21, 6)->nullable();
//            $table->decimal('discount_price', 21, 6)->nullable();

            $table->integer('sort')->nullable();

            $table->unique(['tenant_id', 'product_id', 'item_extra_id']);

            $table->timestamps();

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_extra');
    }
}
