<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->enum('method', ['cash', 'mbok', 'fawry', 'bank-transfer', 'cheque']);
            $table->foreignId('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
            $table->foreignId('currency_id')->references('id')->on('currencies')->restrictOnDelete();
            $table->date('date');
            $table->decimal('amount', 14,4);
            $table->decimal('exchange_rate',14,4);
            $table->foreignId('user_id')->references('id')->on('users');
            $table->text('bank_transfer_reference_no')->nullable(); // depends on payment method
            $table->text('cheque_holder_name')->nullable(); // depends on payment method
            $table->enum('cheque_status', ['collected', 'not-collected'])->nullable(); // depends on payment method
            $table->text('statement');
            $table->text('files')->nullable();
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
        Schema::dropIfExists('invoice_payments');
    }
}
