<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_voucher_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('payment_voucher_id')->index()->references('id')->on('payment_vouchers');
            $table->integer('debit_acc4_code')->index();
            $table->integer('credit_acc4_code')->index();
            $table->foreign('debit_acc4_code')->references('code')->on('acc4');
            $table->foreign('credit_acc4_code')->references('code')->on('acc4');
            $table->morphs('model');
            $table->date('date');
            $table->decimal('amount', 21,4);
            $table->decimal('exchange_rate',21,4)->nullable();
            $table->foreignId('user_id')->index()->references('id')->on('users');
            $table->boolean('transaction_completed')->default(0);
            $table->text('bank_transfer_reference_no')->nullable(); // depends on payment method
            $table->text('cheque_holder_name')->nullable(); // depends on payment method
            $table->enum('cheque_status', ['collected', 'not-collected'])->nullable(); // depends on payment method
            $table->text('statement');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_voucher_payments');
    }
};
