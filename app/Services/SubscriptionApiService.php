<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Subscription;
use Carbon\Carbon;
use InvalidArgumentException;

class SubscriptionApiService
{
    public static function instance(): self
    {
        return new self();
    }

    public function resolveClient(?Client $client = null): Client
    {
        $client ??= auth('sanctum')->user()?->client;

        if (! $client) {
            throw new InvalidArgumentException(__('messages.unauthorized'));
        }

        $client->loadMissing(['subscription.plan', 'user']);

        return $client;
    }

    public function summary(?Client $client = null): array
    {
        $client = $this->resolveClient($client);
        $subscription = $client->subscription;
        $plan = $subscription?->plan;

        return [
            'subscription' => $subscription ? $this->subscriptionPayload($subscription) : null,
            'plan' => $plan ? $this->planPayload($plan) : null,
            'trial' => $this->trialPayload($client),
            'nextBillingDate' => $this->nextBillingDate($subscription, $plan)?->toIso8601String(),
        ];
    }

    public function summaryForUser(?Client $client = null): ?array
    {
        if (! $client) {
            $user = auth('sanctum')->user();

            if (! $user?->hasRole(\App\Models\User::ROLE_CLIENT)) {
                return null;
            }

            $client = $user->client;
        }

        if (! $client) {
            return null;
        }

        $client->loadMissing(['subscription.plan']);
        $plan = $client->subscription?->plan;

        return [
            'planCode' => $plan?->code,
            'billingPeriod' => SubscriptionPricingService::instance()
                ->normalizeBillingPeriod($client->subscription?->billing_period),
            'trialExpired' => subscription_trial_expired($client),
            'trialDaysRemaining' => subscription_trial_days_remaining($client),
            'accountRestricted' => subscription_account_restricted($client),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function plans(?Client $client = null): array
    {
        $client = $this->resolveClient($client);
        $currentPlanId = $client->subscription?->plan_id;

        return Plan::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get()
            ->map(function (Plan $plan) use ($currentPlanId) {
                $payload = $this->planPayload($plan);
                $payload['isCurrent'] = $currentPlanId === $plan->id;
                $payload['quotes'] = [
                    'monthly' => SubscriptionPricingService::instance()->quote($plan, SubscriptionPricingService::BILLING_MONTHLY),
                    'yearly' => SubscriptionPricingService::instance()->quote($plan, SubscriptionPricingService::BILLING_YEARLY),
                ];

                return $payload;
            })
            ->values()
            ->all();
    }

    public function quote(int $planId, ?string $billingPeriod, ?string $couponCode = null, ?Client $client = null): array
    {
        $client = $this->resolveClient($client);
        $plan = Plan::query()->where('active', true)->findOrFail($planId);
        $period = SubscriptionPricingService::instance()->normalizeBillingPeriod($billingPeriod);
        $quote = SubscriptionPricingService::instance()->quote($plan, $period);

        if (filled($couponCode)) {
            $coupon = SubscriptionCouponService::instance()->findUsable($couponCode, $client);
            $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
            $quote['couponCode'] = $coupon->code;
            $quote['couponId'] = $coupon->id;
        }

        return $quote;
    }

    public function usage(?Client $client = null): array
    {
        $client = $this->resolveClient($client);
        $plan = get_plan_for_client($client);
        $types = [
            'sales_invoices',
            'purchase_invoices',
            'orders',
            'price_offers',
            'supply_orders',
            'expenses',
            'companies',
            'users',
        ];

        $limits = [];

        foreach ($types as $type) {
            $max = subscription_plan_limit($type, $plan);
            $used = subscription_resource_count($type, $client);

            $limits[] = [
                'type' => $type,
                'used' => $used,
                'max' => $max,
                'isMaxed' => subscription_resource_maxed_out($type, $client),
            ];
        }

        return ['limits' => $limits];
    }

    public function subscribe(int $planId, ?string $billingPeriod, ?string $couponCode = null, ?Client $client = null): Subscription
    {
        $client = $this->resolveClient($client);
        $plan = Plan::query()->where('active', true)->findOrFail($planId);
        $period = SubscriptionPricingService::instance()->normalizeBillingPeriod($billingPeriod);

        $coupon = null;

        if (filled($couponCode)) {
            $coupon = SubscriptionCouponService::instance()->findUsable($couponCode, $client);
        }

        return Subscription::subscribe($plan, $client, $period, $coupon);
    }

    protected function subscriptionPayload(Subscription $subscription): array
    {
        return [
            'planId' => $subscription->plan_id,
            'billingPeriod' => SubscriptionPricingService::instance()
                ->normalizeBillingPeriod($subscription->billing_period),
            'startDate' => $subscription->start_date?->toIso8601String(),
            'price' => (float) $subscription->price,
            'priceExTax' => (float) ($subscription->price_ex_tax ?? 0),
            'taxAmount' => (float) ($subscription->tax_amount ?? 0),
            'taxPercent' => (float) ($subscription->tax_percent ?? 0),
            'couponCode' => $subscription->coupon_code,
            'discountAmount' => $subscription->discount_amount !== null
                ? (float) $subscription->discount_amount
                : null,
        ];
    }

    protected function planPayload(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'span' => $plan->span,
            'spanDuration' => $plan->span_duration,
            'enableStore' => (bool) $plan->enable_store,
            'enableRoles' => (bool) $plan->enable_roles,
            'isFeatured' => plan_is_featured($plan),
            'restrictAccountAfterDays' => (int) $plan->restrict_account_after_days,
            'maxAllowedSalesInvoices' => (int) $plan->max_allowed_sales_invoices,
            'maxAllowedPurchaseInvoices' => (int) $plan->max_allowed_purchase_invoices,
            'maxAllowedOrders' => (int) $plan->max_allowed_orders,
            'maxAllowedPriceOffers' => (int) $plan->max_allowed_price_offers,
            'maxAllowedSupplyOrders' => (int) $plan->max_allowed_supply_orders,
            'maxAllowedExpenses' => (int) ($plan->max_allowed_expenses ?? -1),
            'maxAllowedUsers' => plan_user_limit($plan),
        ];
    }

    protected function trialPayload(Client $client): array
    {
        return [
            'daysRemaining' => subscription_trial_days_remaining($client),
            'expiresAt' => subscription_trial_expires_at($client)?->toIso8601String(),
            'expired' => subscription_trial_expired($client),
            'accountRestricted' => subscription_account_restricted($client),
        ];
    }

    protected function nextBillingDate(?Subscription $subscription, ?Plan $plan): ?Carbon
    {
        if (! $subscription?->start_date || ! $plan) {
            return null;
        }

        if ($plan->span === Plan::SPAN_ONE_TIME || $plan->span_duration === 'unlimited') {
            return null;
        }

        if ((float) $plan->price === 0.0) {
            return null;
        }

        $start = Carbon::parse($subscription->start_date);
        $period = SubscriptionPricingService::instance()
            ->normalizeBillingPeriod($subscription->billing_period ?: $plan->span_duration);

        return match ($period) {
            SubscriptionPricingService::BILLING_YEARLY => $start->copy()->addYear(),
            default => $start->copy()->addMonth(),
        };
    }
}
