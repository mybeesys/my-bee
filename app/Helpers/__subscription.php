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
