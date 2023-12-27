<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::firstOrCreate(
            [
                'name->en' => 'Trial',
                'name->ar' => 'إشتراك تجريبي',
            ],
            [
                'name' => [
                    'en' => 'Trial',
                    'ar' => 'إشتراك تجريبي',
                ],
                'price' => 0,
                'span' => Plan::SPAN_ONE_TIME,
                'span_in_days' => -1,
                'max_allowed_companies' => 2,
                'max_allowed_purchase_invoices' => 10,
                'max_allowed_sales_invoices' => 10,
                'restrict_account_after_days' => 14,
                'active' => 1,
            ]
        );

        Plan::firstOrCreate([
            'name->en' => 'Monthly',
            'name->ar' => 'شهري',
        ],
            [
                'name' => [
                    'en' => 'Monthly',
                    'ar' => 'شهري',
                ],
                'price' => 250,
                'span' => Plan::SPAN_SPECIFIED,
                'span_in_days' => 30,
                'max_allowed_companies' => 2,
                'max_allowed_purchase_invoices' => 25,
                'max_allowed_sales_invoices' => 25,
                'restrict_account_after_days' => -1,
                'active' => 1,
            ]
        );

        Plan::firstOrCreate([
            'name->en' => 'Annually',
            'name->ar' => 'سنوي',
        ],
            [
                'name' => [
                    'en' => 'Annually',
                    'ar' => 'سنوي',
                ],
                'price' => 2500,
                'span' => Plan::SPAN_SPECIFIED,
                'span_in_days' => 365,
                'max_allowed_companies' => 2,
                'max_allowed_purchase_invoices' => 25,
                'max_allowed_sales_invoices' => 25,
                'restrict_account_after_days' => -1,
                'active' => 1,
            ]
        );
    }
}
