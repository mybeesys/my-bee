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
        return get_plan_for_client(get_client());
    }
}

if (!function_exists('get_plan_for_client')) {
    function get_plan_for_client(\App\Models\Client $client): \App\Models\Plan
    {
        return $client->subscription->plan;
    }
}

if (!function_exists('plan_allows_store')) {
    function plan_allows_store(): bool
    {
        return (bool) get_plan()->enable_store;
    }
}

if (!function_exists('plan_allows_multiple_users')) {
    function plan_allows_multiple_users(): bool
    {
        return subscription_plan_limit('users') > 1;
    }
}

if (!function_exists('subscription_store_plan')) {
    function subscription_store_plan(): ?\App\Models\Plan
    {
        return \App\Models\Plan::query()
            ->where('code', \App\Models\Plan::CODE_COMPLETE)
            ->where('active', true)
            ->first();
    }
}

if (!function_exists('subscription_trial_days')) {
    function subscription_trial_days(?\App\Models\Client $client = null): ?int
    {
        if ($client === null) {
            $client = get_client();
        }

        $days = (int) get_plan_for_client($client)->restrict_account_after_days;

        return $days > 0 ? $days : null;
    }
}

if (!function_exists('subscription_trial_expires_at')) {
    function subscription_trial_expires_at(?\App\Models\Client $client = null): ?\Carbon\Carbon
    {
        if ($client === null) {
            $client = get_client();
        }

        $days = subscription_trial_days($client);

        if ($days === null) {
            return null;
        }

        $subscription = $client->subscription;

        if (! $subscription?->start_date) {
            return null;
        }

        return \Carbon\Carbon::parse($subscription->start_date)->addDays($days)->endOfDay();
    }
}

if (!function_exists('subscription_trial_expired')) {
    function subscription_trial_expired(?\App\Models\Client $client = null): bool
    {
        $expiresAt = subscription_trial_expires_at($client);

        return $expiresAt !== null && now()->greaterThan($expiresAt);
    }
}

if (!function_exists('subscription_account_restricted')) {
    function subscription_account_restricted(?\App\Models\Client $client = null): bool
    {
        return subscription_trial_expired($client);
    }
}

if (!function_exists('subscription_trial_days_remaining')) {
    function subscription_trial_days_remaining(?\App\Models\Client $client = null): ?int
    {
        $expiresAt = subscription_trial_expires_at($client);

        if ($expiresAt === null) {
            return null;
        }

        if (now()->greaterThan($expiresAt)) {
            return 0;
        }

        return (int) now()->diffInDays($expiresAt, false) + 1;
    }
}

if (!function_exists('plan_user_limit')) {
    function plan_user_limit(?\App\Models\Plan $plan = null): int
    {
        $plan ??= get_plan();

        return match ($plan->code) {
            \App\Models\Plan::CODE_COMPLETE => 2,
            \App\Models\Plan::CODE_FREE, \App\Models\Plan::CODE_BUSINESS => 1,
            default => max(1, (int) $plan->max_allowed_users),
        };
    }
}

if (!function_exists('plan_is_featured')) {
    function plan_is_featured(?\App\Models\Plan $plan = null): bool
    {
        $plan ??= get_plan();

        return $plan->code === \App\Models\Plan::CODE_COMPLETE;
    }
}

if (!function_exists('subscription_plan_limit')) {
    function subscription_plan_limit(string $type, ?\App\Models\Plan $plan = null): int
    {
        $plan ??= get_plan();

        if ($type === 'users') {
            return plan_user_limit($plan);
        }

        $column = match ($type) {
            'sales_invoices' => 'max_allowed_sales_invoices',
            'purchase_invoices' => 'max_allowed_purchase_invoices',
            'orders' => 'max_allowed_orders',
            'price_offers' => 'max_allowed_price_offers',
            'supply_orders' => 'max_allowed_supply_orders',
            'expenses' => 'max_allowed_expenses',
            'companies' => 'max_allowed_companies',
            default => null,
        };

        if ($column === null) {
            return -1;
        }

        return (int) $plan->{$column};
    }
}

if (!function_exists('subscription_resource_count')) {
    function subscription_resource_count(string $type, ?\App\Models\Client $client = null): int
    {
        if ($client === null) {
            $client = get_client();
        }

        return match ($type) {
            'sales_invoices' => \App\Models\Invoice::query()
                ->sales()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->where('temp', false)
                ->listedInSalesModule()
                ->count(),
            'purchase_invoices' => \App\Models\Invoice::query()
                ->purchases()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->where('temp', false)
                ->count(),
            'orders' => \App\Models\Order::query()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->count(),
            'price_offers' => \App\Models\PriceOffer::query()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->count(),
            'supply_orders' => \App\Models\SupplyOrder::query()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->count(),
            'expenses' => \App\Models\Expense::query()
                ->whereRelation('tenant', 'client_id', $client->id)
                ->count(),
            'companies' => \App\Models\Tenant::whereBelongsTo($client)->count(),
            'users' => \App\Models\User::query()
                ->where(function ($query) use ($client) {
                    $query->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('client_id', $client->id))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('id', $client->id));
                })
                ->distinct()
                ->count('users.id'),
            default => 0,
        };
    }
}

if (!function_exists('subscription_resource_maxed_out')) {
    function subscription_resource_maxed_out(string $type, ?\App\Models\Client $client = null): bool
    {
        if ($client === null) {
            $client = get_client();
        }

        $max = subscription_plan_limit($type, get_plan_for_client($client));

        return $max > 0 && subscription_resource_count($type, $client) >= $max;
    }
}

if (!function_exists('companies_maxed_out')) {
    function companies_maxed_out(): bool
    {
        return subscription_resource_maxed_out('companies');
    }
}

if (!function_exists('users_maxed_out')) {
    function users_maxed_out(): bool
    {
        return subscription_resource_maxed_out('users');
    }
}

if (!function_exists('sales_invoices_maxed_out')) {
    function sales_invoices_maxed_out(): bool
    {
        return subscription_resource_maxed_out('sales_invoices');
    }
}

if (!function_exists('purchases_invoices_maxed_out')) {
    function purchases_invoices_maxed_out(): bool
    {
        return subscription_resource_maxed_out('purchase_invoices');
    }
}

if (!function_exists('orders_maxed_out')) {
    function orders_maxed_out(): bool
    {
        return subscription_resource_maxed_out('orders');
    }
}

if (!function_exists('price_offers_maxed_out')) {
    function price_offers_maxed_out(): bool
    {
        return subscription_resource_maxed_out('price_offers');
    }
}

if (!function_exists('supply_orders_maxed_out')) {
    function supply_orders_maxed_out(): bool
    {
        return subscription_resource_maxed_out('supply_orders');
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
    function subscription_limit_usage(string $type, ?\App\Models\Client $client = null): ?array
    {
        if ($client === null) {
            $client = get_client();
        }

        $max = subscription_plan_limit($type, get_plan_for_client($client));
        $used = subscription_resource_count($type, $client);
        $isMaxed = subscription_resource_maxed_out($type, $client);

        $keys = match ($type) {
            'sales_invoices' => [
                'title' => 'fields.sales_invoices_maxed_out_title',
                'body' => 'fields.sales_invoices_maxed_out_body',
                'hint' => 'fields.sales_invoices_maxed_out_hint',
                'upgrade' => 'fields.sales_invoices_maxed_out_upgrade',
            ],
            'purchase_invoices' => [
                'title' => 'fields.purchase_invoices_maxed_out_title',
                'body' => 'fields.purchase_invoices_maxed_out_body',
                'hint' => 'fields.purchase_invoices_maxed_out_hint',
                'upgrade' => 'fields.purchase_invoices_maxed_out_upgrade',
            ],
            'orders' => [
                'title' => 'fields.orders_maxed_out_title',
                'body' => 'fields.orders_maxed_out_body',
                'hint' => 'fields.orders_maxed_out_hint',
                'upgrade' => 'fields.orders_maxed_out_upgrade',
            ],
            'price_offers' => [
                'title' => 'fields.price_offers_maxed_out_title',
                'body' => 'fields.price_offers_maxed_out_body',
                'hint' => 'fields.price_offers_maxed_out_hint',
                'upgrade' => 'fields.price_offers_maxed_out_upgrade',
            ],
            'supply_orders' => [
                'title' => 'fields.supply_orders_maxed_out_title',
                'body' => 'fields.supply_orders_maxed_out_body',
                'hint' => 'fields.supply_orders_maxed_out_hint',
                'upgrade' => 'fields.supply_orders_maxed_out_upgrade',
            ],
            'users' => [
                'title' => 'fields.users_maxed_out_title',
                'body' => 'fields.users_maxed_out_body',
                'hint' => 'fields.users_maxed_out_hint',
                'upgrade' => 'fields.users_maxed_out_upgrade',
            ],
            default => [],
        };

        if ($max <= 0 || ! $isMaxed || $keys === []) {
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

if (!function_exists('subscription_limit_exceeded_message')) {
    function subscription_limit_exceeded_message(string $type, ?\App\Models\Client $client = null): ?string
    {
        $usage = subscription_limit_usage($type, $client);

        return $usage['body'] ?? null;
    }
}

if (!function_exists('subscription_pricing')) {
    /**
     * @return array{
     *     billing_period: string,
     *     monthly_price: float,
     *     months: int,
     *     discount_months: int,
     *     subtotal_ex_tax: float,
     *     tax_percent: float,
     *     tax_amount: float,
     *     total_inc_tax: float,
     *     is_free: bool,
     *     currency: string
     * }
     */
    function subscription_pricing(\App\Models\Plan $plan, ?string $billingPeriod = 'monthly'): array
    {
        return \App\Services\SubscriptionPricingService::instance()->quote($plan, $billingPeriod);
    }
}

if (!function_exists('subscription_vat_percent')) {
    function subscription_vat_percent(): float
    {
        return \App\Services\SubscriptionPricingService::instance()->vatPercent();
    }
}
