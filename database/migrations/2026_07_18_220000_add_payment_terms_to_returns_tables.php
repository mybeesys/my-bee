<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->string('payment_terms', 16)->default('cash')->after('notes');
            $table->unsignedBigInteger('refund_acc4_code')->nullable()->after('payment_terms');
        });

        Schema::table('purchases_returns', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('invoice_id')
                ->index()
                ->references('id')
                ->on('suppliers');

            $table->foreignId('invoice_id')->nullable()->change();

            $table->string('payment_terms', 16)->default('cash')->after('notes');
            $table->unsignedBigInteger('refund_acc4_code')->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'refund_acc4_code']);
        });

        Schema::table('purchases_returns', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'payment_terms', 'refund_acc4_code']);

            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
