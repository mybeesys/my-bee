<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');

            $table->string('no');
            $table->enum('status', ['pending', 'delivered', 'cancelled'])->default('pending');
            $table->enum('delivery_type', ['delivery', 'pickup'])->default('delivery');
            $table->text('receipt')->nullable();
            $table->string('delivery_address')->nullable();
            $table->decimal('discount', 14, 4)->default(0);
            $table->decimal('delivery', 14, 4)->default(2000);
            $table->decimal('delivery_extra', 14, 4)->default(0);
            $table->foreignId('user_id')->nullable()->references('id')->on('users');
            $table->foreignId('client_id')->references('id')->on('clients');
            $table->foreignId('invoice_id')->references('id')->on('invoices');
            $table->foreignId('driver_id')->nullable()->references('id')->on('drivers');
            $table->dateTime('delivery_date')->nullable();
            $table->dateTime('canceled_date')->nullable();
            $table->dateTime('canceled_reason')->nullable();
            $table->dateTime('paid_date')->nullable();
            $table->decimal('paid_amount', 14, 4)->default(0);
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
        Schema::dropIfExists('orders');
    }
}
