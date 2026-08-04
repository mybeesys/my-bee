<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['code' => Plan::CODE_FREE],
            [
                'name' => [
                    'en' => 'Free',
                    'ar' => 'مجاني',
                ],
                'price' => 0,
                'span' => Plan::SPAN_SPECIFIED,
                'span_duration' => 'monthly',
                'max_allowed_companies' => -1,
                'max_allowed_users' => 1,
                'max_allowed_sales_invoices' => 5,
                'max_allowed_purchase_invoices' => 5,
                'max_allowed_orders' => 5,
                'max_allowed_price_offers' => 5,
                'max_allowed_supply_orders' => 5,
                'max_allowed_expenses' => 5,
                'restrict_account_after_days' => 15,
                'enable_roles' => false,
                'enable_store' => false,
                'active' => true,
                'sort_order' => 1,
                'is_featured' => false,
            ]
        );

        Plan::query()->updateOrCreate(
            ['code' => Plan::CODE_BUSINESS],
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
                'max_allowed_expenses' => -1,
                'restrict_account_after_days' => -1,
                'enable_roles' => true,
                'enable_store' => false,
                'active' => true,
                'sort_order' => 2,
                'is_featured' => false,
            ]
        );

        Plan::query()->updateOrCreate(
            ['code' => Plan::CODE_COMPLETE],
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
                'max_allowed_expenses' => -1,
                'restrict_account_after_days' => -1,
                'enable_roles' => true,
                'enable_store' => true,
                'active' => true,
                'sort_order' => 3,
                'is_featured' => true,
            ]
        );
    }
}
