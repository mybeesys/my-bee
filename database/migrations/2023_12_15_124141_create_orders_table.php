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

            $table->enum('source', ['shop', 'dashboard'])->default('shop');
            $table->string('payment_method')->default('cash_on_delivery')->index();
            $table->string('no');
            $table->enum('status', ['new', 'packaging', 'delivery-in-progress', 'completed', 'cancelled'])->default('new');
            $table->enum('delivery_type', ['none', 'delivery', 'pickup'])->default('delivery');
            $table->decimal('discount', 21, 6)->default(0);
            $table->decimal('delivery', 21, 6)->default(0);
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_extra', 21, 6)->default(0);
            $table->foreignId('user_id')->nullable()->index()->references('id')->on('users'); //from panel?
            $table->foreignId('customer_id')->index()->references('id')->on('customers');
            $table->foreignId('invoice_id')->nullable()->index()->references('id')->on('invoices');
            $table->dateTime('delivery_date')->nullable();
            $table->dateTime('canceled_date')->nullable();
            $table->text('canceled_reason')->nullable();
            $table->dateTime('paid_date')->nullable();
            $table->decimal('paid_amount', 21, 6)->default(0);
            $table->text('notes')->nullable();

            $table->foreignId('state_id')->nullable()->index()->references('id')->on('states');
            $table->foreignId('city_id')->nullable()->index()->references('id')->on('cities');
            $table->foreignId('area_id')->nullable()->index()->references('id')->on('areas');

            $table->foreignId('coupon_id')->nullable()->index()->references('id')->on('coupons');

            $table->text('coupon_data')->nullable();

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
