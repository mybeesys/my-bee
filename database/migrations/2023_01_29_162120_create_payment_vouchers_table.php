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
            $table->foreignId('op_id')->references('id')->on('op');
            $table->foreignId('currency_id')->references('id')->on('currencies');
            $table->integer('debit_acc4_code')->nullable()->index();
            $table->integer('credit_acc4_code')->nullable()->index();
            $table->foreign('debit_acc4_code')->references('code')->on('acc4');
            $table->foreign('credit_acc4_code')->references('code')->on('acc4');
            $table->enum('payment_method', ['cash', 'bank-transfer', 'cheque']);
            $table->text('no');
            $table->date('date');
            $table->float('amount');
            $table->float('ex_rate')->nullable();
            $table->text('bank_transfer_reference_no')->nullable(); // depends on payment method
            $table->text('received_by');
            $table->text('cheque_holder_name')->nullable(); // depends on payment method
            $table->enum('cheque_status', ['collected', 'not-collected'])->nullable(); // depends on payment method
            $table->text('statement');
            $table->text('files')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('checked_at')->nullable();
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
