<?php

use App\Services\HyperPay\HyperPaySettingsInstaller;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hyperpay_payments', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 24)->unique();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('billing_period', 20);
            $table->foreignId('platform_coupon_id')->nullable()->constrained('platform_coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('merchant_transaction_id', 64)->unique();
            $table->string('checkout_id', 64)->nullable()->index();
            $table->string('integrity', 191)->nullable();
            $table->string('payment_id', 64)->nullable()->index();
            $table->string('brand', 32)->nullable();
            $table->string('result_code', 64)->nullable();
            $table->text('result_description')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->string('resource_path', 255)->nullable();
            $table->json('billing')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('gateway_response')->nullable();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'billing_given_name')) {
                $table->string('billing_given_name', 80)->nullable()->after('address');
                $table->string('billing_surname', 80)->nullable()->after('billing_given_name');
                $table->string('billing_email')->nullable()->after('billing_surname');
                $table->string('billing_street1', 191)->nullable()->after('billing_email');
                $table->string('billing_city', 80)->nullable()->after('billing_street1');
                $table->string('billing_state', 80)->nullable()->after('billing_city');
                $table->string('billing_country', 2)->nullable()->after('billing_state');
                $table->string('billing_postcode', 16)->nullable()->after('billing_country');
            }
        });

        HyperPaySettingsInstaller::install();
    }

    public function down(): void
    {
        Schema::dropIfExists('hyperpay_payments');

        Schema::table('clients', function (Blueprint $table) {
            foreach ([
                'billing_given_name',
                'billing_surname',
                'billing_email',
                'billing_street1',
                'billing_city',
                'billing_state',
                'billing_country',
                'billing_postcode',
            ] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Setting::query()
            ->whereNull('tenant_id')
            ->where(function ($query) {
                $query->where('key', 'like', 'hyperpay.%')
                    ->orWhere('key', 'like', 'settings.tabs.hyperpay.%');
            })
            ->delete();
    }
};
