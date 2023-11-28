<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockMovementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->foreignId('target_warehouse_id')->references('id')->on('warehouses');
            $table->foreignId('destination_warehouse_id')->references('id')->on('warehouses');
            $table->morphs('item');
            $table->integer('qty');
            $table->integer('target_warehouse_pre_movement_qty'); //product count in warehouse
            $table->integer('target_warehouse_post_movement_qty'); //product count in warehouse
            $table->integer('destination_warehouse_pre_movement_qty');//product count in warehouse
            $table->integer('destination_warehouse_post_movement_qty');//product count in warehouse
            $table->text('stocks');
            $table->date('date');
            $table->foreignId('user_id')->references('id')->on('users');
            $table->decimal('movement_expenses', 14,4)->default(0);
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
        Schema::dropIfExists('stock_movements');
    }
}
