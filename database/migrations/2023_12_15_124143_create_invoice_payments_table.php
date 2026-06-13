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
            $table->enum('method', ['cash', 'bank-transfer', 'cheque']);
            $table->foreignId('invoice_id')->index()->references('id')->on('invoices');
            $table->date('date');
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
            $table->decimal('amount', 21,4);
            $table->decimal('exchange_rate',21,4)->nullable();
            $table->foreignId('user_id')->index()->references('id')->on('users');
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
