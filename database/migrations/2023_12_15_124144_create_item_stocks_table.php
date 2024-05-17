<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no')->unique();
            $table->enum('type', ['opening-stock', 'purchase', 'moved']);
            $table->morphs('item');

            $table->foreignId('product_id')->index()->references('id')->on('products'); //just to link all stocks with product

            $table->foreignId('warehouse_id')->nullable()->references('id')->on('warehouses');
            $table->foreignId('invoice_id')->nullable()->references('id')->on('invoices');
           //if stock is moved
            $table->foreignId('stock_id')->nullable()->index()->references('id')->on('item_stocks');
            $table->foreignId('user_id')->index()->references('id')->on('users');
            $table->date('date');
            $table->integer('qty_in');
            $table->integer('qty_out')->default(0);
            $table->integer('qty_moved')->default(0);
            $table->decimal('unit_cost', 21, 6);
            $table->date('expiration_date')->nullable();
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('item_stocks');
    }
}
