<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptVouchersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('receipt_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no')->unique();
            $table->enum('for', ['customer', 'other_entity']);
            $table->foreignId('invoice_id')->nullable()->index()->references('id')->on('invoices');
            $table->integer('acc4_code')->index();
            $table->foreign('acc4_code')->references('code')->on('acc4');
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
        Schema::dropIfExists('receipt_vouchers');
    }
}
