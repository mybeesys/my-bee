<?php

namespace App\Livewire;

use App\Filament\Tenant\Pages\Subscription as SubscriptionPage;
use App\Models\Plan;
use App\Models\PlatformCoupon;
use App\Models\Subscription;
use App\Services\SubscriptionCouponService;
use App\Services\SubscriptionPricingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ManageSubscription extends Component
{
    public ?int $selectedPlanId = null;

    public string $billingPeriod = SubscriptionPricingService::BILLING_MONTHLY;

    public bool $showConfirmModal = false;

    public string $couponCode = '';

    public ?int $appliedCouponId = null;

    public function mount(): void
    {
        $subscription = get_subscription();
        $this->selectedPlanId = $subscription?->plan_id;
        $this->billingPeriod = SubscriptionPricingService::instance()
            ->normalizeBillingPeriod($subscription?->billing_period);

        if (session()->pull('subscription_updated')) {
            fns()->sendSuccess(__('fields.subscription_updated'));
        }
    }

    public function updatedBillingPeriod(string $value): void
    {
        $this->billingPeriod = SubscriptionPricingService::instance()->normalizeBillingPeriod($value);
        $this->showConfirmModal = false;
        $this->clearAppliedCoupon(false);
    }

    public function updatedSelectedPlanId(): void
    {
        $this->showConfirmModal = false;
        $this->clearAppliedCoupon(false);
    }

    public function getHasActiveCouponsProperty(): bool
    {
        return SubscriptionCouponService::instance()->hasActiveCoupons();
    }

    public function applyCoupon(): void
    {
        $client = get_client();

        if (! $client || ! $this->hasActiveCoupons) {
            return;
        }

        try {
            $coupon = SubscriptionCouponService::instance()->findUsable($this->couponCode, $client);
            $this->appliedCouponId = $coupon->id;
            $this->couponCode = $coupon->code;
            fns()->sendSuccess(__('fields.subscription_coupon_applied'));
        } catch (\InvalidArgumentException $e) {
            $this->clearAppliedCoupon(false);
            fns()->sendWarning($e->getMessage());
        }
    }

    public function clearAppliedCoupon(bool $notify = true): void
    {
        $hadCoupon = filled($this->appliedCouponId) || filled($this->couponCode);
        $this->appliedCouponId = null;
        $this->couponCode = '';

        if ($notify && $hadCoupon) {
            fns()->sendSuccess(__('fields.subscription_coupon_cleared'));
        }
    }

    public function getCurrentSubscriptionProperty(): ?Subscription
    {
        return get_subscription();
    }

    public function getCurrentPlanProperty(): ?Plan
    {
        $subscription = $this->currentSubscription;

        return $subscription?->plan;
    }

    /** @return Collection<int, Plan> */
    public function getPlansProperty(): Collection
    {
        return Plan::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
    }

    public function openConfirmModal(): void
    {
        $plan = Plan::query()->find($this->selectedPlanId);

        if (! $plan) {
            return;
        }

        if ($this->currentSubscription?->plan_id === $plan->id
            && SubscriptionPricingService::instance()->normalizeBillingPeriod($this->currentSubscription->billing_period) === $this->billingPeriod
        ) {
            fns()->sendWarning(__('fields.subscription_already_on_plan'));

            return;
        }

        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
    }

    public function confirmUpdateSubscription(): void
    {
        $this->showConfirmModal = false;
        $this->updateSubscription();
    }

    public function updateSubscription(): void
    {
        $plan = Plan::query()->find($this->selectedPlanId);

        if (! $plan) {
            return;
        }

        $client = get_client();

        if (! $client) {
            return;
        }

        if ($this->currentSubscription?->plan_id === $plan->id
            && SubscriptionPricingService::instance()->normalizeBillingPeriod($this->currentSubscription->billing_period) === $this->billingPeriod
        ) {
            fns()->sendWarning(__('fields.subscription_already_on_plan'));

            return;
        }

        $coupon = null;

        if ($this->appliedCouponId) {
            try {
                $couponModel = PlatformCoupon::query()->find($this->appliedCouponId);
                $coupon = $couponModel
                    ? SubscriptionCouponService::instance()->findUsable($couponModel->code, $client)
                    : null;
            } catch (\InvalidArgumentException $e) {
                $this->clearAppliedCoupon(false);
                fns()->sendWarning($e->getMessage());

                return;
            }
        }

        Subscription::subscribe($plan, $client, $this->billingPeriod, $coupon);

        session()->flash('subscription_updated', true);

        $this->redirect(SubscriptionPage::getUrl(), navigate: false);
    }

    public function planSpanLabel(Plan $plan): string
    {
        if ($plan->span === Plan::SPAN_ONE_TIME) {
            return __('fields.one_time_subscription');
        }

        return match ($this->billingPeriod) {
            SubscriptionPricingService::BILLING_YEARLY => __('fields.yearly'),
            default => __('fields.monthly'),
        };
    }

    public function planPriceSuffix(Plan $plan): string
    {
        if ((float) $plan->price === 0.0) {
            if ($plan->restrict_account_after_days > 0) {
                return __('fields.subscription_trial_days_suffix', ['days' => $plan->restrict_account_after_days]);
            }

            return __('fields.subscription_forever_free');
        }

        if ($plan->span === Plan::SPAN_ONE_TIME) {
            return '';
        }

        return match ($this->billingPeriod) {
            SubscriptionPricingService::BILLING_YEARLY => __('fields.subscription_per_year'),
            default => __('fields.subscription_per_month'),
        };
    }

    public function planQuote(Plan $plan, ?string $billingPeriod = null): array
    {
        $quote = subscription_pricing($plan, $billingPeriod ?? $this->billingPeriod);

        if (! $this->appliedCouponId) {
            return $quote;
        }

        $coupon = PlatformCoupon::query()->find($this->appliedCouponId);

        if (! $coupon) {
            $this->clearAppliedCoupon(false);

            return $quote;
        }

        return SubscriptionCouponService::instance()->applyToQuote($quote, $coupon);
    }

    public function formatPlanPrice(Plan $plan, ?string $billingPeriod = null): string
    {
        $quote = $this->planQuote($plan, $billingPeriod);

        if ($quote['is_free']) {
            return __('fields.free');
        }

        return SubscriptionPricingService::instance()->formatMoney($quote['subtotal_ex_tax'], $quote['currency']);
    }

    public function planTagline(Plan $plan): string
    {
        return match ($plan->code) {
            Plan::CODE_FREE => __('fields.plan_tagline_free'),
            Plan::CODE_BUSINESS => __('fields.plan_tagline_business'),
            Plan::CODE_COMPLETE => __('fields.plan_tagline_complete'),
            default => __('fields.subscription_change_plan_hint'),
        };
    }

    public function planTier(Plan $plan): string
    {
        return match ($plan->code) {
            Plan::CODE_FREE => 'free',
            Plan::CODE_BUSINESS => 'business',
            Plan::CODE_COMPLETE => 'complete',
            default => match (true) {
                (float) $plan->price === 0.0 => 'free',
                $plan->enable_store => 'complete',
                default => 'business',
            },
        };
    }

    public function planIsFeatured(Plan $plan): bool
    {
        return plan_is_featured($plan);
    }

    public function planUserCount(Plan $plan): int
    {
        return plan_user_limit($plan);
    }

    public function planTierLabel(Plan $plan): string
    {
        return match ($this->planTier($plan)) {
            'free' => __('fields.plan_tier_starter'),
            'business' => __('fields.plan_tier_growth'),
            'complete' => __('fields.plan_tier_premium'),
            default => '',
        };
    }

    /**
     * @return array{
     *     groups: array<int, array{
     *         label: string,
     *         compact: bool,
     *         status?: string,
     *         display?: string,
     *         modules?: array<int, string>,
     *         items?: array<int, array{label: string, status: string, display: string}>
     *     }>,
     *     extras: array<int, array{label: string, status: string, display: string}>
     * }
     */
    public function planFeatureGroups(Plan $plan): array
    {
        $groups = [];

        $salesItems = array_values(array_filter([
            $this->makeLimitFeature(__('fields.sales_invoices'), $plan->max_allowed_sales_invoices),
            $this->makeLimitFeature(__('fields.sales_returns'), $plan->max_allowed_sales_invoices),
            $this->makeLimitFeature(__('fields.price_offers'), $plan->max_allowed_price_offers),
        ]));

        if ($group = $this->buildFeatureGroup(__('fields.nav_group_sales'), $salesItems)) {
            $groups[] = $group;
        }

        $purchaseItems = array_values(array_filter([
            $this->makeLimitFeature(__('fields.purchases_invoices'), $plan->max_allowed_purchase_invoices),
            $this->makeLimitFeature(__('fields.purchases_returns'), $plan->max_allowed_purchase_invoices),
            $this->makeLimitFeature(__('fields.supply_orders'), $plan->max_allowed_supply_orders),
        ]));

        if ($group = $this->buildFeatureGroup(__('fields.nav_group_purchases'), $purchaseItems)) {
            $groups[] = $group;
        }

        $expenseItem = $this->makeLimitFeature(__('fields.expenses'), $plan->max_allowed_expenses ?? -1);

        if ($group = $this->buildFeatureGroup(__('fields.expenses'), array_filter([$expenseItem]))) {
            $groups[] = $group;
        }

        if ($plan->enable_store) {
            $storeItems = array_values(array_filter([
                $this->makeIncludedFeature(__('fields.plan_feature_online_store')),
                $this->makeLimitFeature(__('fields.orders'), $plan->max_allowed_orders),
            ]));

            if ($group = $this->buildFeatureGroup(__('fields.nav_group_online_store'), $storeItems)) {
                $groups[] = $group;
            }
        }

        $extras = [];

        if ($plan->restrict_account_after_days > 0 && (float) $plan->price === 0.0) {
            $extras[] = [
                'label' => __('fields.plan_feature_trial_period'),
                'status' => 'included',
                'display' => __('fields.subscription_trial_days_label', ['days' => $plan->restrict_account_after_days]),
            ];
        }

        $extras[] = [
            'label' => __('fields.plan_feature_full_erp'),
            'status' => 'included',
            'display' => __('fields.included'),
        ];

        $extras[] = [
            'label' => __('fields.plan_feature_users_label'),
            'status' => 'limited',
            'display' => $this->formatUserCount($plan),
        ];

        return [
            'groups' => $groups,
            'extras' => $extras,
        ];
    }

    /**
     * @param  array<int, array{label: string, status: string, display: string}>  $items
     * @return array{
     *     label: string,
     *     compact: bool,
     *     status?: string,
     *     display?: string,
     *     modules?: array<int, string>,
     *     items?: array<int, array{label: string, status: string, display: string}>
     * }|null
     */
    protected function buildFeatureGroup(string $groupLabel, array $items): ?array
    {
        $items = array_values($items);

        if ($items === []) {
            return null;
        }

        if ($this->shouldCompactFeatureItems($items)) {
            $modules = count($items) > 1
                ? array_column($items, 'label')
                : [];

            return [
                'label' => $groupLabel,
                'compact' => true,
                'status' => $items[0]['status'],
                'display' => $items[0]['display'],
                'modules' => $modules,
            ];
        }

        return [
            'label' => $groupLabel,
            'compact' => false,
            'items' => $items,
        ];
    }

    /** @param  array<int, array{label: string, status: string, display: string}>  $items */
    protected function shouldCompactFeatureItems(array $items): bool
    {
        if (count($items) === 1) {
            return true;
        }

        $first = $items[0];

        foreach ($items as $item) {
            if ($item['status'] !== $first['status'] || $item['display'] !== $first['display']) {
                return false;
            }
        }

        return true;
    }

    /** @return array{label: string, status: string, display: string}|null */
    protected function makeLimitFeature(string $label, ?int $limit): ?array
    {
        $status = $this->limitStatus($limit);

        if ($status === 'excluded') {
            return null;
        }

        return [
            'label' => $label,
            'status' => $status,
            'display' => $this->formatLimit($limit),
        ];
    }

    /** @return array{label: string, status: string, display: string} */
    protected function makeIncludedFeature(string $label): array
    {
        return [
            'label' => $label,
            'status' => 'included',
            'display' => __('fields.included'),
        ];
    }

    protected function formatUserCount(Plan $plan): string
    {
        return (string) plan_user_limit($plan);
    }

    /** @deprecated Use planFeatureGroups() */
    public function planFeatures(Plan $plan, ?Plan $previousPlan = null): array
    {
        unset($previousPlan);

        $sections = $this->planFeatureGroups($plan);

        return collect($sections['groups'])
            ->flatMap(function (array $group) {
                if ($group['compact'] ?? false) {
                    return [[
                        'label' => $group['label'],
                        'status' => $group['status'],
                        'display' => $group['display'],
                    ]];
                }

                return $group['items'] ?? [];
            })
            ->merge($sections['extras'])
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function getPlanChangeSummaryProperty(): ?array
    {
        $currentPlan = $this->currentPlan;
        $selectedPlan = $this->plans->firstWhere('id', $this->selectedPlanId);

        if (! $currentPlan || ! $selectedPlan) {
            return null;
        }

        if ($this->isCurrentSelection($selectedPlan)) {
            return null;
        }

        return $this->buildPlanChangeSummary($currentPlan, $selectedPlan);
    }

    public function isCurrentSelection(?Plan $plan = null): bool
    {
        $plan ??= $this->plans->firstWhere('id', $this->selectedPlanId);
        $subscription = $this->currentSubscription;

        if (! $plan || ! $subscription) {
            return false;
        }

        return (int) $subscription->plan_id === (int) $plan->id
            && SubscriptionPricingService::instance()->normalizeBillingPeriod($subscription->billing_period) === $this->billingPeriod;
    }

    /** @return array<string, mixed> */
    protected function buildPlanChangeSummary(Plan $from, Plan $to): array
    {
        $gains = [];
        $losses = [];

        foreach ($this->planLimitDefinitions() as $field => $label) {
            $comparison = $this->compareLimits($from->{$field}, $to->{$field});

            if ($comparison > 0) {
                $gains[] = $this->formatLimitChange($label, $from->{$field}, $to->{$field}, true);
            } elseif ($comparison < 0) {
                $losses[] = $this->formatLimitChange($label, $from->{$field}, $to->{$field}, false);
            }
        }

        if (! $from->enable_store && $to->enable_store) {
            $gains[] = [
                'label' => __('fields.plan_feature_online_store'),
                'detail' => __('fields.subscription_confirm_feature_included'),
            ];
        } elseif ($from->enable_store && ! $to->enable_store) {
            $losses[] = [
                'label' => __('fields.plan_feature_online_store'),
                'detail' => __('fields.subscription_confirm_feature_removed'),
            ];
        }

        if (! $from->enable_roles && $to->enable_roles) {
            $gains[] = [
                'label' => __('fields.plan_feature_users_label'),
                'detail' => __('fields.subscription_confirm_roles_enabled'),
            ];
        } elseif ($from->enable_roles && ! $to->enable_roles) {
            $losses[] = [
                'label' => __('fields.plan_feature_users_label'),
                'detail' => __('fields.subscription_confirm_roles_disabled'),
            ];
        }

        if ($from->max_allowed_users < $to->max_allowed_users) {
            $gains[] = [
                'label' => __('fields.plan_feature_users_label'),
                'detail' => __('fields.subscription_confirm_users_gain', [
                    'to' => max(1, $to->max_allowed_users),
                    'from' => max(1, $from->max_allowed_users),
                ]),
            ];
        } elseif ($from->max_allowed_users > $to->max_allowed_users) {
            $losses[] = [
                'label' => __('fields.plan_feature_users_label'),
                'detail' => __('fields.subscription_confirm_users_loss', [
                    'to' => max(1, $to->max_allowed_users),
                    'from' => max(1, $from->max_allowed_users),
                ]),
            ];
        }

        return [
            'direction' => $this->planChangeDirection($from, $to),
            'from' => $from,
            'to' => $to,
            'from_tier' => $this->planTier($from),
            'to_tier' => $this->planTier($to),
            'gains' => $gains,
            'losses' => $losses,
            'price_change' => $this->planPriceChangeSummary($from, $to),
        ];
    }

    /** @return array<string, string> */
    protected function planLimitDefinitions(): array
    {
        return [
            'max_allowed_sales_invoices' => __('fields.sales_invoices'),
            'max_allowed_purchase_invoices' => __('fields.purchases_invoices'),
            'max_allowed_orders' => __('fields.orders'),
            'max_allowed_price_offers' => __('fields.price_offers'),
            'max_allowed_supply_orders' => __('fields.supply_orders'),
            'max_allowed_expenses' => __('fields.expenses'),
        ];
    }

    protected function planChangeDirection(Plan $from, Plan $to): string
    {
        $pricing = SubscriptionPricingService::instance();
        $fromTotal = $pricing->quote(
            $from,
            $pricing->normalizeBillingPeriod($this->currentSubscription?->billing_period)
        )['total_inc_tax'];
        $toTotal = $pricing->quote($to, $this->billingPeriod)['total_inc_tax'];

        if ((float) $toTotal !== (float) $fromTotal) {
            return (float) $toTotal > (float) $fromTotal ? 'upgrade' : 'downgrade';
        }

        if ((int) $to->sort_order !== (int) $from->sort_order) {
            return (int) $to->sort_order > (int) $from->sort_order ? 'upgrade' : 'downgrade';
        }

        return 'lateral';
    }

    protected function compareLimits(?int $from, ?int $to): int
    {
        $score = static function (?int $value): int {
            if ($value === null || $value < 0) {
                return PHP_INT_MAX;
            }

            return $value;
        };

        $fromScore = $score($from);
        $toScore = $score($to);

        if ($toScore > $fromScore) {
            return 1;
        }

        if ($toScore < $fromScore) {
            return -1;
        }

        return 0;
    }

    /** @return array{label: string, detail: string} */
    protected function formatLimitChange(string $label, ?int $from, ?int $to, bool $isGain): array
    {
        return [
            'label' => $label,
            'detail' => $isGain
                ? __('fields.subscription_confirm_limit_gain', [
                    'to' => $this->formatLimit($to),
                    'from' => $this->formatLimit($from),
                ])
                : __('fields.subscription_confirm_limit_loss', [
                    'to' => $this->formatLimit($to),
                    'from' => $this->formatLimit($from),
                ]),
        ];
    }

    /** @return array{label: string, value: string}|null */
    protected function planPriceChangeSummary(Plan $from, Plan $to): ?array
    {
        $pricing = SubscriptionPricingService::instance();
        $toQuote = $pricing->quote($to, $this->billingPeriod);
        $fromPeriod = $pricing->normalizeBillingPeriod($this->currentSubscription?->billing_period);
        $fromQuote = $pricing->quote($from, $fromPeriod);
        $suffix = $this->planPriceSuffix($to);

        if ($toQuote['is_free']) {
            return [
                'label' => __('fields.subscription_confirm_price_free'),
                'value' => __('fields.free'),
            ];
        }

        $formattedPrice = $pricing->formatMoney($toQuote['total_inc_tax'], $toQuote['currency']);
        $breakdown = __('fields.subscription_price_breakdown_short', [
            'ex_tax' => $pricing->formatMoney($toQuote['subtotal_ex_tax'], $toQuote['currency']),
            'tax' => $pricing->formatMoney($toQuote['tax_amount'], $toQuote['currency']),
            'vat' => rtrim(rtrim(number_format($toQuote['tax_percent'], 2, '.', ''), '0'), '.'),
        ]);

        if ((float) $fromQuote['total_inc_tax'] === (float) $toQuote['total_inc_tax']
            && $fromPeriod === $this->billingPeriod
        ) {
            return [
                'label' => __('fields.subscription_confirm_price_same'),
                'value' => trim($formattedPrice . ' ' . $suffix) . ' — ' . $breakdown,
            ];
        }

        if ((float) $toQuote['total_inc_tax'] > (float) $fromQuote['total_inc_tax']) {
            return [
                'label' => __('fields.subscription_confirm_price_increase'),
                'value' => trim($formattedPrice . ' ' . $suffix) . ' — ' . $breakdown,
            ];
        }

        return [
            'label' => __('fields.subscription_confirm_price_decrease'),
            'value' => trim($formattedPrice . ' ' . $suffix) . ' — ' . $breakdown,
        ];
    }

    public function nextBillingDate(): ?Carbon
    {
        $subscription = $this->currentSubscription;
        $plan = $this->currentPlan;

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

    protected function limitStatus(?int $value): string
    {
        if ($value === null || $value < 0) {
            return 'unlimited';
        }

        return 'limited';
    }

    protected function limitIsUpgrade(?int $current, ?int $previous): bool
    {
        if ($current === null || $current < 0) {
            return $previous !== null && $previous >= 0;
        }

        if ($previous === null || $previous < 0) {
            return false;
        }

        return $current > $previous;
    }

    protected function formatLimit(?int $value): string
    {
        if ($value === null || $value < 0) {
            return __('fields.unlimited');
        }

        return (string) $value;
    }

    public function render()
    {
        return view('livewire.manage-subscription');
    }
}
