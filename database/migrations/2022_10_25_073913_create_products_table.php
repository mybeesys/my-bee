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
            $table->string('barcode')->index()->nullable();
            $table->foreignId('category_id')->index()->references('id')->on('categories');
            $table->foreignId('main_unit_id')->nullable()->references('id')->on('units');
            $table->decimal('main_unit_price', 19, 4)->nullable();
//            $table->foreignId('medium_unit_id')->nullable()->references('id')->on('units');
//            $table->foreignId('large_unit_id')->nullable()->references('id')->on('units');
            $table->integer('security_stock')->default(0);
            $table->text('description')->nullable();
            $table->text('attributes')->nullable();

            $table->boolean('enable_variations')->default(0);
            $table->boolean('free_delivery')->default(0);

            $table->boolean('taxable')->default(1);
            $table->foreignId('tax_profile_id')->nullable()->references('id')->on('tax_profiles');

            $table->boolean('eligible_for_discounts')->default(1);
            $table->boolean('published')->default(1);

            $table->unique(['name', 'tenant_id']);
            $table->unique(['barcode', 'tenant_id']);

            $table->timestamps();
        });

        Schema::create('product_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->string('barcode')->index()->nullable();
            $table->foreignId('product_id')->index()->references('id')->on('products');
            $table->foreignId('unit_id')->index()->references('id')->on('units');
            $table->integer('unit_count_from_main_unit');

            $table->unique(['tenant_id', 'product_id', 'unit_id']);
            $table->unique(['product_id', 'unit_id']);

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
        Schema::dropIfExists('product_unit');
    }
}
