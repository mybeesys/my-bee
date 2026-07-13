<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('invoice_id')
                ->index()
                ->references('id')
                ->on('customers');

            $table->foreignId('invoice_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');

            $table->foreignId('invoice_id')->nullable(false)->change();
        });
    }
};
