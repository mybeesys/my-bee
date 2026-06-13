<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'payment_terms')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_method')) {
                $table->string('payment_terms', 16)
                    ->default('credit')
                    ->after('payment_method')
                    ->index();
            } else {
                $table->string('payment_terms', 16)
                    ->default('credit')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'payment_terms')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_terms');
        });
    }
};
