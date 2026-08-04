<?php

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
            $table->boolean('enable_store')->default(false)->after('enable_roles');
            $table->integer('max_allowed_orders')->default(-1)->after('max_allowed_sales_invoices');
            $table->integer('max_allowed_price_offers')->default(-1)->after('max_allowed_orders');
            $table->integer('max_allowed_supply_orders')->default(-1)->after('max_allowed_price_offers');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('active');
            $table->boolean('is_featured')->default(false)->after('sort_order');
        });

        $this->syncSubscriptionTiers();
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'enable_store',
                'max_allowed_orders',
                'max_allowed_price_offers',
                'max_allowed_supply_orders',
                'sort_order',
                'is_featured',
            ]);
        });
    }

    private function syncSubscriptionTiers(): void
    {
        $free = Plan::query()->updateOrCreate(
            ['code' => 'free'],
            [
                'name' => [
                    'en' => 'Free',
                    'ar' => 'مجاني',
                ],
                'price' => 0,
                'span' => Plan::SPAN_SPECIFIED,
                'span_duration' => 'monthly',
                'max_allowed_companies' => -1,
                'max_allowed_users' => -1,
                'max_allowed_sales_invoices' => 5,
                'max_allowed_purchase_invoices' => 5,
                'max_allowed_orders' => 5,
                'max_allowed_price_offers' => 5,
                'max_allowed_supply_orders' => 5,
                'restrict_account_after_days' => -1,
                'enable_roles' => false,
                'enable_store' => false,
                'active' => true,
                'sort_order' => 1,
                'is_featured' => false,
            ]
        );

        $business = Plan::query()->updateOrCreate(
            ['code' => 'business'],
            [
                'name' => [
                    'en' => 'Launch',
                    'ar' => 'انطلاقة',
                ],
                'price' => 75,
                'span' => Plan::SPAN_SPECIFIED,
                'span_duration' => 'monthly',
                'max_allowed_companies' => -1,
                'max_allowed_users' => 1,
                'max_allowed_sales_invoices' => -1,
                'max_allowed_purchase_invoices' => -1,
                'max_allowed_orders' => -1,
                'max_allowed_price_offers' => -1,
                'max_allowed_supply_orders' => -1,
                'restrict_account_after_days' => -1,
                'enable_roles' => true,
                'enable_store' => false,
                'active' => true,
                'sort_order' => 2,
                'is_featured' => false,
            ]
        );

        $complete = Plan::query()->updateOrCreate(
            ['code' => 'complete'],
            [
                'name' => [
                    'en' => 'Growth',
                    'ar' => 'نمو',
                ],
                'price' => 100,
                'span' => Plan::SPAN_SPECIFIED,
                'span_duration' => 'monthly',
                'max_allowed_companies' => -1,
                'max_allowed_users' => 2,
                'max_allowed_sales_invoices' => -1,
                'max_allowed_purchase_invoices' => -1,
                'max_allowed_orders' => -1,
                'max_allowed_price_offers' => -1,
                'max_allowed_supply_orders' => -1,
                'restrict_account_after_days' => -1,
                'enable_roles' => true,
                'enable_store' => true,
                'active' => true,
                'sort_order' => 3,
                'is_featured' => true,
            ]
        );

        Plan::query()
            ->where(function ($query) {
                $query->whereNull('code')
                    ->orWhereNotIn('code', ['free', 'business', 'complete']);
            })
            ->get()
            ->each(function (Plan $legacyPlan) use ($free, $business, $complete): void {
                $target = match (true) {
                    (float) $legacyPlan->price >= 100 => $complete,
                    (float) $legacyPlan->price > 0 => $business,
                    default => $free,
                };

                Subscription::query()
                    ->where('plan_id', $legacyPlan->id)
                    ->update(['plan_id' => $target->id]);

                $legacyPlan->update(['active' => false]);
            });
    }
};
