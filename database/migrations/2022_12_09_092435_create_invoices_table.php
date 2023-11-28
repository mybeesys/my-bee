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
            $table->text('no');
            $table->enum('type', ['purchases', 'sales'])->index();
            $table->enum('for', ['customer', 'representative', 'supplier'])->index();
            $table->enum('discount_option', ['overall', 'per-item', 'none'])->default('none');
            $table->enum('discount_method', ['amount', 'percent', 'none'])->default('none');
            $table->decimal('discount_amount', 19, 4)->nullable();
            $table->decimal('discount_percent', 19, 4)->nullable();
//            $table->integer('purchases_invoice_no')->nullable();
//            $table->integer('sales_invoice_no')->nullable();
            $table->foreignId('purchase_status_id')->nullable()->index()->references('id')->on('purchase_invoice_statuses');
            $table->foreignId('sale_status_id')->nullable()->index()->references('id')->on('purchase_invoice_statuses');

            $table->foreignId('user_id')->index()->references('id')->on('users')->restrictOnDelete();

            $table->foreignId('customer_id')->index()->nullable()->references('id')->on('customers')->restrictOnDelete();
//            $table->foreignId('order_id')->index()->nullable()->references('id')->on('orders')->restrictOnDelete();
            $table->foreignId('representative_id')->index()->nullable()->references('id')->on('representatives')->restrictOnDelete();
            $table->foreignId('supplier_id')->index()->nullable()->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->index()->nullable()->references('id')->on('users');
            $table->foreignId('locked_by_id')->index()->nullable()->references('id')->on('users');
            $table->date('date')->index();
            $table->timestamp('locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('meta')->nullable();
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
        Schema::dropIfExists('invoices');
    }
}
