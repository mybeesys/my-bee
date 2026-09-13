<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('current_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('billing_period', 20);
            $table->foreignId('platform_coupon_id')->nullable()->constrained('platform_coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('quoted_price_ex_tax', 21, 8)->nullable();
            $table->decimal('quoted_tax_amount', 21, 8)->nullable();
            $table->decimal('quoted_tax_percent', 8, 4)->nullable();
            $table->decimal('quoted_discount_amount', 21, 8)->nullable();
            $table->decimal('quoted_total', 21, 8)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_requests');
    }
};
