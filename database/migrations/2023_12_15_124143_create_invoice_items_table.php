<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('invoice_id')->index()->references('id')->on('invoices')->restrictOnDelete();
            $table->foreignId('product_id')->index()->references('id')->on('products')->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->index()->references('id')->on('units')->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->index()->references('id')->on('product_variants')->restrictOnDelete();
            $table->foreignId('order_details_id')->nullable()->index()->references('id')->on('order_details')->restrictOnDelete();

//            $table->foreignId('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreignId('tax_profile_id')->nullable()->index()->references('id')->on('tax_profiles');

            $table->text('stocks')->nullable(); //sales invoice only, taken from stocks ids??
            $table->text('inventory_taken_from_warehouses')->nullable(); //sales invoice only, taken from warehouses ids??
            $table->boolean('inventory_returned')->default(0); //sales invoice only, in case of order cancelled or returned
            $table->integer('qty');
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
            $table->decimal('price', 19, 4);
            $table->decimal('discount', 19,4)->default(0);
            $table->decimal('exchange_rate', 19, 4)->nullable();
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->longText("meta")->nullable(); //save tax profile data here
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
        Schema::dropIfExists('invoice_items');
    }
}
