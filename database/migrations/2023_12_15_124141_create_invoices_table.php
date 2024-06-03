<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no');
            $table->string('uid')->unique();
            $table->enum('status', ['purchase_order', 'sale_order', 'cancelled', 'confirmed'])->index();
            $table->enum('type', ['purchases', 'sales'])->index();
            $table->string('payment_method')->index()->default("cash_on_delivery"); //cash_on_delivery
            $table->string('transaction_ref')->nullable();
            $table->enum('for', ['customer', 'representative', 'supplier'])->index();
            $table->foreignId('warehouse_id')->nullable()->index()->references('id')->on('warehouses');

            $table->enum('discount_option', ['overall', 'per-item', 'none'])->default('none');
            $table->enum('discount_method', ['amount', 'percent', 'none'])->default('none');
            $table->decimal('discount_amount', 19, 4)->nullable();
            $table->decimal('discount_percent', 19, 4)->nullable();

            $table->foreignId('user_id')->nullable()->index()->references('id')->on('users')->restrictOnDelete();

            $table->foreignId('customer_id')->index()->nullable()->references('id')->on('customers')->restrictOnDelete();
//            $table->foreignId('order_id')->index()->nullable()->references('id')->on('orders')->restrictOnDelete();
            $table->foreignId('representative_id')->index()->nullable()->references('id')->on('representatives')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->index()->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->index()->nullable()->references('id')->on('users');
            $table->foreignId('locked_by_id')->index()->nullable()->references('id')->on('users');
            $table->date('date')->index();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('meta')->nullable();
            $table->boolean('temp')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'no']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
