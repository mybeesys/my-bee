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
            $table->enum('type', ['opening-stock', 'purchase', 'moved']);
            $table->morphs('item');
            $table->foreignId('unit_id')->index()->references('id')->on('units');
            $table->foreignId('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->references('id')->on('invoices')->restrictOnDelete();
           //if stock is moved
            $table->foreignId('stock_id')->nullable()->index()->references('id')->on('item_stocks')->restrictOnDelete();
            $table->foreignId('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->date('date');
            $table->integer('qty_in');
            $table->integer('qty_out')->default(0);
            $table->integer('qty_moved')->default(0);
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
            $table->decimal('unit_cost', 19, 4);
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
