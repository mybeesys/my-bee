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

            $table->morphs('item');

            $table->integer('qty');

            $table->decimal('discount', 21, 6)->default(0);
            $table->decimal('tax', 21, 6)->default(0);

            $table->integer('taken_qty')->default(0);

            $table->decimal('unit_price', 21, 6);

            $table->boolean('cancelled')->default(0);
            $table->timestamp('cancelled_date')->nullable();

            $table->foreignId('user_id')->nullable()->index()->references('id')->on('users'); //from panel?

            $table->foreignId('cancelled_by_id')->nullable()->index()->references('id')->on('users');

            $table->text('tax_profile_data')->nullable();

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
