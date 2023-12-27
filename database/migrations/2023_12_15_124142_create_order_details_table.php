<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('order_id')->index()->references('id')->on('orders');

            $table->enum('type', ['basic', 'units', 'variants']);

            $table->string('display_name');

            $table->morphs('item');
            $table->integer('qty');
            $table->integer('taken_qty')->default(0);
            $table->decimal('unit_price', 19, 4);
            $table->boolean('cancelled')->default(0);

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
        Schema::dropIfExists('order_details');
    }
}
