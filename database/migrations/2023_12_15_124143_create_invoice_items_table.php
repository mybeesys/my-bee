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
            $table->foreignId('invoice_id')->index()->references('id')->on('invoices');
            $table->foreignId('product_id')->index()->references('id')->on('products');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('order_details_id')->nullable();

            $table->string('name');
            //item currently not working by warehouse feature: only use it when requested
            $table->foreignId('warehouse_id')->nullable()->index()->references('id')->on('warehouses');
            $table->foreignId('tax_profile_id')->nullable()->index()->references('id')->on('tax_profiles');

            $table->text('tax_profile_data')->nullable();

            $table->text('stocks')->nullable(); //sales invoice only, taken from stocks ids??
            $table->text('inventory_taken_from_warehouses')->nullable(); //sales invoice only, taken from warehouses ids??
            $table->integer('qty');
            $table->decimal('price', 21, 6);
            $table->decimal('tax', 21,6)->default(0);
            $table->decimal('discount', 21,6)->default(0);
            $table->date('warranty_start_date')->nullable();
            $table->date('warranty_end_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->longText("meta")->nullable(); //save tax profile data here

            $table->foreignId('user_id')->nullable()->index()->references('id')->on('users')->restrictOnDelete();

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
