<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('unit_id')->nullable()->index()->references('id')->on('units'); //could be a variant

            $table->integer('acc4_code')->index();
            $table->foreign('acc4_code')->references('code')->on('acc4')->restrictOnDelete();
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
            $table->decimal('unit_cost', 19, 4)->nullable();
            $table->decimal('price', 19, 4);
            $table->decimal('discount_price', 19, 4)->nullable();
            $table->decimal('exchange_rate',19, 4)->nullable();
            $table->timestamp('date');
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
        Schema::dropIfExists('item_prices');
    }
}
