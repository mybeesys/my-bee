<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOpTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('op', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->references('id')->on('tenants');
            $table->string('no');
            $table->enum('type', [
                'general-voucher',  //قيد عام
                'opening-credit', //رصيد إفتتاحي
                'cash-receipt-voucher', //سند قبض نقدي
                'bank-transfer-receipt-voucher', //سند قبض بتحويل بنكي
                'cash-payment-voucher', //سند صرف نقدي
                'bank-transfer-payment-voucher', //سند صرف بتحويل بنكي
                'cheque-receipt-voucher', //سند قبض شيك
                'cheque-payment-voucher', //سند صرف شيك
                'currency-purchase', //شراء عملة
                'currency-sale', //بيع عملة
                'taxes',
            ]);
            $table->string('payment_voucher_no')->nullable();
            $table->foreignId('user_id')->index()->references('id')->on('users');
            $table->date('date');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('files')->nullable();

            $table->unique(['tenant_id', 'no']);

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
        Schema::dropIfExists('ops');
    }
}
