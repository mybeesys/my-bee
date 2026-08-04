<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_period', 20)->default('monthly')->after('price');
            $table->decimal('price_ex_tax', 21, 8)->nullable()->after('billing_period');
            $table->decimal('tax_amount', 21, 8)->nullable()->after('price_ex_tax');
            $table->decimal('tax_percent', 8, 4)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['billing_period', 'price_ex_tax', 'tax_amount', 'tax_percent']);
        });
    }
};
