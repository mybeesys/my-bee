<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'pending_plan_id')) {
                $table->foreignId('pending_plan_id')->nullable()->after('user_id')->constrained('plans')->nullOnDelete();
                $table->string('pending_billing_period', 20)->nullable()->after('pending_plan_id');
            }
        });

        Schema::table('hyperpay_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('hyperpay_payments', 'source')) {
                $table->string('source', 32)->default('subscription')->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'pending_plan_id')) {
                $table->dropConstrainedForeignId('pending_plan_id');
                $table->dropColumn('pending_billing_period');
            }
        });

        Schema::table('hyperpay_payments', function (Blueprint $table) {
            if (Schema::hasColumn('hyperpay_payments', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
