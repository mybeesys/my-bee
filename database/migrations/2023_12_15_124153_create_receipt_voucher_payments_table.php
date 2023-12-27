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
        Schema::create('receipt_voucher_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('receipt_voucher_id')->index()->references('id')->on('receipt_vouchers');
            $table->enum('method', ['cash', 'bank-transfer', 'cheque']);
            $table->morphs('model');
            $table->date('date');
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
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
        Schema::dropIfExists('receipt_voucher_payments');
    }
};
