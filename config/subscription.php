<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription VAT percent
    |--------------------------------------------------------------------------
    |
    | Plan prices are stored exclusive of tax. The final payable amount always
    | adds this VAT percentage. Override via setting('subscription_vat_percent').
    |
    */
    'vat_percent' => (float) env('SUBSCRIPTION_VAT_PERCENT', 15),

    /*
    |--------------------------------------------------------------------------
    | Yearly billing
    |--------------------------------------------------------------------------
    |
    | Yearly subscriptions are billed as 10 months of the monthly price
    | (two months discounted automatically).
    |
    */
    'yearly_paid_months' => 10,
    'yearly_discount_months' => 2,
];
