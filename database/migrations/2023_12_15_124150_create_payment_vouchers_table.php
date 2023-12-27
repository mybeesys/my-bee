<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no')->unique();
            $table->enum('for', ['supplier', 'customer']); //
            $table->integer('debit_acc4_code')->index();
            $table->integer('credit_acc4_code')->index();
            $table->foreign('debit_acc4_code')->references('code')->on('acc4');
            $table->foreign('credit_acc4_code')->references('code')->on('acc4');
            $table->foreignId('op_id')->references('id')->on('op');
            $table->foreignId('invoice_id')->nullable()->index()->references('id')->on('invoices');
            $table->foreignId('customer_id')->nullable()->index()->references('id')->on('customers');
            $table->foreignId('supplier_id')->index()->nullable()->references('id')->on('suppliers');
            $table->date('date');
            $table->foreignId('user_id')->index()->references('id')->on('users');
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
        Schema::dropIfExists('payment_vouchers');
    }
}
