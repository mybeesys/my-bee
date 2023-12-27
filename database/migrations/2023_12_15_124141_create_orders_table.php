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
            $table->enum('status', ['new', 'ready', 'delivery-in-progress', 'completed', 'cancelled'])->default('new');
            $table->enum('delivery_type', ['none', 'delivery', 'pickup'])->default('delivery');
            $table->string('delivery_address');
            $table->decimal('discount', 19, 4)->default(0);
            $table->decimal('delivery', 19, 4)->default(2000);
            $table->decimal('delivery_extra', 14, 4)->default(0);
            $table->foreignId('user_id')->nullable()->references('id')->on('users');
            $table->foreignId('customer_id')->index()->references('id')->on('customers');
            $table->foreignId('invoice_id')->nullable()->index()->references('id')->on('invoices');
            $table->dateTime('delivery_date')->nullable();
            $table->dateTime('canceled_date')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->dateTime('paid_date')->nullable();
            $table->decimal('paid_amount', 19, 4)->default(0);
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
