<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('accounting_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->enum('type', ['general-voucher'])->default('general-voucher');
            $table->string('reference');
            $table->string('account_credit');
            $table->string('account_debit');
            $table->decimal('credit')->nullable();
            $table->decimal('debit')->nullable();
            $table->morphs('credit_owner');
            $table->morphs('debit_owner');
            $table->text('description');
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
        Schema::dropIfExists('accounting_transactions');
    }
}
