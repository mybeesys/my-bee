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
        Plan::create(
            [
                'name' => [
                    'en' => 'Free',
                    'ar' => 'مجاني',
                ],
                'price' => 0,
                'span' => Plan::SPAN_ONE_TIME,
                'span_in_days' => -1,
                'max_allowed_companies' => 2,
                'max_allowed_purchase_invoices' => 25,
                'max_allowed_sales_invoices' => 25,
                'active' => 1
            ]
        );
    }
}
