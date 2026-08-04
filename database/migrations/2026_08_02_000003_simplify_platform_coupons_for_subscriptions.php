<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_coupons', function (Blueprint $table) {
            if (Schema::hasColumn('platform_coupons', 'span')) {
                $table->dropColumn('span');
            }
        });

        // Normalize type to percent|fixed (table is empty in local; keep safe for other envs).
        if (Schema::hasColumn('platform_coupons', 'type')) {
            DB::table('platform_coupons')
                ->whereNotIn('type', ['percent', 'fixed'])
                ->update(['type' => 'percent']);

            Schema::table('platform_coupons', function (Blueprint $table) {
                $table->string('type', 20)->default('percent')->change();
            });
        }

        Schema::table('platform_coupons', function (Blueprint $table) {
            $table->decimal('value', 21, 8)->change();
            $table->dateTime('valid_until')->nullable()->change();
        });

        Schema::create('platform_coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_coupon_id')->constrained('platform_coupons')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['platform_coupon_id', 'client_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('platform_coupon_id')
                ->nullable()
                ->after('tax_percent')
                ->constrained('platform_coupons')
                ->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('platform_coupon_id');
            $table->decimal('discount_amount', 21, 8)->nullable()->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('platform_coupon_id');
            $table->dropColumn(['coupon_code', 'discount_amount']);
        });

        Schema::dropIfExists('platform_coupon_redemptions');

        Schema::table('platform_coupons', function (Blueprint $table) {
            $table->enum('span', ['one_time', 'specified_time', 'unlimited_time'])->nullable()->after('code');
        });
    }
};
