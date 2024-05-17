<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCashDetTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cash_det', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->foreignId('op_id')->index()->references('id')->on('op');
            $table->string('currency_iso_code')->index();
            $table->foreign('currency_iso_code')->references('iso_code')->on('currencies');
            $table->foreignId('invoice_id')->index()->nullable()->references('id')->on('invoices');
            $table->integer('transaction_id')->nullable(); //link for the double entry
            $table->integer('account_code')->index();
            $table->foreign('account_code')->references('code')->on('acc4');
            $table->date('date');
            $table->decimal('amount_in', 21, 6)->index();
            $table->decimal('amount_out', 21, 6)->index();
            $table->decimal('balance_pre_transaction', 21, 6);
            $table->decimal('balance_post_transaction', 21, 6);
            $table->decimal('exchange_rate')->nullable();
            $table->text('statement')->nullable();
            $table->text('meta')->nullable();
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
        Schema::dropIfExists('journal_entries');
    }
}
