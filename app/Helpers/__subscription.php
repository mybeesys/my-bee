<?php

if (!function_exists('get_subscription')) {
    function get_subscription()
    {
        return get_client()->subscription;
    }
}

if (!function_exists('get_plan')) {
    function get_plan(): \App\Models\Plan
    {
        return get_subscription()->plan;
    }
}

if (!function_exists('companies_maxed_out')) {
    function companies_maxed_out()
    {
        $plan = get_plan();
        return $plan->max_allowed_companies > 0 and \App\Models\Tenant::whereBelongsTo(get_client())->count() >= $plan->max_allowed_companies;
    }
}

if (!function_exists('users_maxed_out')) {
    function users_maxed_out()
    {
        $plan = get_plan();
        return $plan->max_allowed_users > 0 and \App\Models\User::whereHasOne(get_client())->count() >= $plan->max_allowed_users;
    }
}

if (!function_exists('sales_invoices_maxed_out')) {
    function sales_invoices_maxed_out()
    {
        $plan = get_plan();
        return $plan->max_allowed_sales_invoices > 0 and \App\Models\Invoice::sales()->currentClient()->count() >= $plan->max_allowed_sales_invoices;
    }
}

if (!function_exists('purchases_invoices_maxed_out')) {
    function purchases_invoices_maxed_out()
    {
        $plan = get_plan();
        return $plan->max_allowed_purchase_invoices > 0 and \App\Models\Invoice::purchases()->currentClient()->count() >= $plan->max_allowed_purchase_invoices;
    }
}

if (!function_exists('subscription_limit_usage')) {
    /**
     * @return array{
     *     used: int,
     *     max: int,
     *     percent: float,
     *     title: string,
     *     body: string,
     *     hint: string,
     *     upgrade_label: string,
     * }|null
     */
    function subscription_limit_usage(string $type): ?array
    {
        $plan = get_plan();

        [$used, $max, $isMaxed, $keys] = match ($type) {
            'sales_invoices' => [
                \App\Models\Invoice::sales()->currentClient()->count(),
                (int) $plan->max_allowed_sales_invoices,
                sales_invoices_maxed_out(),
                [
                    'title' => 'fields.sales_invoices_maxed_out_title',
                    'body' => 'fields.sales_invoices_maxed_out_body',
                    'hint' => 'fields.sales_invoices_maxed_out_hint',
                    'upgrade' => 'fields.sales_invoices_maxed_out_upgrade',
                ],
            ],
            'purchase_invoices' => [
                \App\Models\Invoice::purchases()->currentClient()->count(),
                (int) $plan->max_allowed_purchase_invoices,
                purchases_invoices_maxed_out(),
                [
                    'title' => 'fields.purchase_invoices_maxed_out_title',
                    'body' => 'fields.purchase_invoices_maxed_out_body',
                    'hint' => 'fields.purchase_invoices_maxed_out_hint',
                    'upgrade' => 'fields.purchase_invoices_maxed_out_upgrade',
                ],
            ],
            default => [0, 0, false, []],
        };

        if ($max <= 0 || ! $isMaxed) {
            return null;
        }

        $replacements = ['used' => $used, 'max' => $max];

        return [
            'used' => $used,
            'max' => $max,
            'percent' => min(100, ($used / max($max, 1)) * 100),
            'title' => __($keys['title']),
            'body' => __($keys['body'], $replacements),
            'hint' => __($keys['hint']),
            'upgrade_label' => __($keys['upgrade']),
        ];
    }
}
