<div class="subscription-page">
    @php
        $subscription = $this->currentSubscription;
        $currentPlan = $this->currentPlan;
        $currency = main_currency_iso_code();
        $nextBilling = $this->nextBillingDate();
        $plansList = $this->plans->values();
    @endphp

    @if ($currentPlan && $subscription)
        @php
            $trialDaysRemaining = subscription_trial_days_remaining();
            $trialExpiresAt = subscription_trial_expires_at();
            $trialExpired = subscription_trial_expired();
        @endphp

        @if ($trialDaysRemaining !== null)
            <div @class([
                'subscription-page__trial-banner',
                'subscription-page__trial-banner--warning' => ! $trialExpired && $trialDaysRemaining <= 5,
                'subscription-page__trial-banner--expired' => $trialExpired,
            ])>
                <x-filament::icon
                    :icon="$trialExpired ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-clock'"
                    class="subscription-page__trial-banner-icon"
                />
                <div>
                    <strong>
                        @if ($trialExpired)
                            {{ __('fields.subscription_trial_expired_title') }}
                        @else
                            {{ __('fields.subscription_trial_active_title', ['days' => $trialDaysRemaining]) }}
                        @endif
                    </strong>
                    <p>
                        @if ($trialExpired)
                            {{ __('fields.subscription_trial_expired_body') }}
                        @else
                            {{ __('fields.subscription_trial_active_body', ['date' => $trialExpiresAt?->format('d/m/Y')]) }}
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <section class="subscription-page__card subscription-page__card--current subscription-page__card--tier-{{ $this->planTier($currentPlan) }}">
            <div class="subscription-page__plan-badges subscription-page__plan-badges--on-card">
                <span class="subscription-page__current-badge">
                    <x-filament::icon icon="heroicon-m-check-badge" class="subscription-page__status-badge-icon" />
                    {{ __('fields.subscription_current_plan') }}
                </span>
            </div>

            <header class="subscription-page__card-header subscription-page__card-header--with-badge">
                <div>
                    <span class="subscription-page__tier-label subscription-page__tier-label--{{ $this->planTier($currentPlan) }}">
                        {{ $this->planTierLabel($currentPlan) }}
                    </span>
                    <h2 class="subscription-page__title">{{ $currentPlan->name }}</h2>
                    <p class="subscription-page__subtitle">{{ $this->planTagline($currentPlan) }}</p>
                </div>
                <span class="subscription-page__badge">{{ __('fields.subscription_active') }}</span>
            </header>

            <div class="subscription-page__card-body">
                <dl class="subscription-page__meta">
                    <div class="subscription-page__meta-item">
                        <dt>{{ __('fields.start_date') }}</dt>
                        <dd>{{ $subscription->start_date ? \Illuminate\Support\Carbon::parse($subscription->start_date)->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div class="subscription-page__meta-item">
                        <dt>{{ __('fields.next_billing_date') }}</dt>
                        <dd>{{ $nextBilling?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="subscription-page__meta-item">
                        <dt>{{ __('fields.span') }}</dt>
                        <dd>
                            {{ \App\Services\SubscriptionPricingService::instance()->normalizeBillingPeriod($subscription->billing_period) === 'yearly'
                                ? __('fields.yearly')
                                : __('fields.monthly') }}
                        </dd>
                    </div>
                </dl>

                @php
                    $currentQuote = subscription_pricing(
                        $currentPlan,
                        $subscription->billing_period ?: 'monthly'
                    );
                @endphp

                <div class="subscription-page__price">
                    <span class="subscription-page__price-value">
                        @if ($currentQuote['is_free'])
                            {{ __('fields.free') }}
                        @else
                            {{ \App\Services\SubscriptionPricingService::instance()->formatMoney((float) ($subscription->price ?? $currentQuote['total_inc_tax']), $currency) }}
                        @endif
                    </span>
                    @if ($suffix = $this->planPriceSuffix($currentPlan))
                        <span class="subscription-page__price-suffix">{{ $suffix }}</span>
                    @endif
                </div>

                @unless ($currentQuote['is_free'])
                    <p class="subscription-page__price-note">
                        {{ __('fields.subscription_prices_ex_tax_note') }}
                    </p>
                    <dl class="subscription-page__price-breakdown">
                        <div>
                            <dt>{{ __('fields.subscription_subtotal_ex_tax') }}</dt>
                            <dd>{{ \App\Services\SubscriptionPricingService::instance()->formatMoney((float) ($subscription->price_ex_tax ?? $currentQuote['subtotal_ex_tax']), $currency) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('fields.subscription_tax_amount', ['vat' => rtrim(rtrim(number_format((float) ($subscription->tax_percent ?? $currentQuote['tax_percent']), 2, '.', ''), '0'), '.')]) }}</dt>
                            <dd>{{ \App\Services\SubscriptionPricingService::instance()->formatMoney((float) ($subscription->tax_amount ?? $currentQuote['tax_amount']), $currency) }}</dd>
                        </div>
                        <div class="subscription-page__price-breakdown-total">
                            <dt>{{ __('fields.subscription_total_inc_tax') }}</dt>
                            <dd>{{ \App\Services\SubscriptionPricingService::instance()->formatMoney((float) ($subscription->price ?? $currentQuote['total_inc_tax']), $currency) }}</dd>
                        </div>
                    </dl>
                @endunless
            </div>
        </section>
    @endif

    <section class="subscription-page__card">
        <header class="subscription-page__card-header">
            <div>
                <p class="subscription-page__eyebrow">{{ __('fields.subscription_change_plan') }}</p>
                <h2 class="subscription-page__title">{{ __('fields.subscription_plans') }}</h2>
                <p class="subscription-page__subtitle">{{ __('fields.subscription_plans_marketing_hint') }}</p>
            </div>
        </header>

        <div class="subscription-page__card-body">
            <div class="subscription-page__billing-toggle" role="group" aria-label="{{ __('fields.subscription_billing_period') }}">
                <button
                    type="button"
                    wire:click="$set('billingPeriod', 'monthly')"
                    @class([
                        'subscription-page__billing-btn',
                        'subscription-page__billing-btn--active' => $billingPeriod === 'monthly',
                    ])
                >
                    {{ __('fields.monthly') }}
                </button>
                <button
                    type="button"
                    wire:click="$set('billingPeriod', 'yearly')"
                    @class([
                        'subscription-page__billing-btn',
                        'subscription-page__billing-btn--active' => $billingPeriod === 'yearly',
                    ])
                >
                    {{ __('fields.yearly') }}
                    <span class="subscription-page__billing-save">{{ __('fields.subscription_yearly_save_badge') }}</span>
                </button>
            </div>

            <p class="subscription-page__price-note subscription-page__price-note--global">
                {{ __('fields.subscription_prices_ex_tax_note') }}
                @if ($billingPeriod === 'yearly')
                    — {{ __('fields.subscription_yearly_discount_note') }}
                @endif
            </p>

            @if ($plansList->isEmpty())
                <p class="subscription-page__empty">{{ __('fields.subscription_no_plans') }}</p>
            @else
                <div class="subscription-page__plans">
                    @foreach ($plansList as $index => $plan)
                        @php
                            $isCurrent = $this->isCurrentSelection($plan);
                            $isSelected = $selectedPlanId === $plan->id;
                            $previousPlan = $index > 0 ? $plansList[$index - 1] : null;
                            $tier = $this->planTier($plan);
                            $featureSections = $this->planFeatureGroups($plan);
                            $isFeatured = $this->planIsFeatured($plan);
                            $quote = $this->planQuote($plan);
                        @endphp

                        <label @class([
                            'subscription-page__plan',
                            'subscription-page__plan--selected' => $isSelected,
                            'subscription-page__plan--current' => $isCurrent,
                            'subscription-page__plan--featured' => $isFeatured,
                            'subscription-page__plan--tier-' . $tier,
                        ])>
                            <input
                                type="radio"
                                name="selectedPlanId"
                                value="{{ $plan->id }}"
                                wire:model.live="selectedPlanId"
                                class="sr-only"
                            />

                            <div @class([
                                'subscription-page__plan-inner',
                                'subscription-page__plan-inner--has-top-badge' => $isFeatured || $isCurrent,
                                'subscription-page__plan-inner--has-bottom-badge' => $isSelected && ! $isCurrent,
                            ])>
                                @if ($isFeatured || $isCurrent)
                                    <div class="subscription-page__plan-badges">
                                        @if ($isFeatured)
                                            <span class="subscription-page__popular-badge">{{ __('fields.subscription_most_popular') }}</span>
                                        @endif

                                        @if ($isCurrent)
                                            <span class="subscription-page__current-badge">
                                                <x-filament::icon icon="heroicon-m-check-badge" class="subscription-page__status-badge-icon" />
                                                {{ __('fields.subscription_your_plan') }}
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                <div class="subscription-page__plan-head">
                                    <div>
                                        <span class="subscription-page__tier-label subscription-page__tier-label--{{ $tier }}">
                                            {{ $this->planTierLabel($plan) }}
                                        </span>
                                        <h3 class="subscription-page__plan-name">{{ $plan->name }}</h3>
                                        <p class="subscription-page__plan-span">{{ $this->planTagline($plan) }}</p>
                                    </div>
                                </div>

                                <div class="subscription-page__plan-price subscription-page__plan-price--{{ $tier }}">
                                    <div class="subscription-page__price-block subscription-page__price-block--compact">
                                        <div class="subscription-page__price-main">
                                            @if ($quote['is_free'])
                                                <span class="subscription-page__price-amount">{{ __('fields.free') }}</span>
                                            @else
                                                <span class="subscription-page__price-amount">
                                                    {{ \App\Services\SubscriptionPricingService::instance()->formatMoney($quote['subtotal_ex_tax'], $quote['currency']) }}
                                                </span>
                                            @endif
                                            @if ($suffix = $this->planPriceSuffix($plan))
                                                <span class="subscription-page__price-cycle">{{ $suffix }}</span>
                                            @endif
                                        </div>
                                        @unless ($quote['is_free'])
                                            <span class="subscription-page__price-incl">{{ __('fields.subscription_price_ex_tax_label') }}</span>
                                        @endunless
                                    </div>
                                </div>

                                <p @class([
                                    'subscription-page__plan-context',
                                    'subscription-page__plan-context--starter' => ! $previousPlan,
                                    'subscription-page__plan-context--upgrade' => (bool) $previousPlan,
                                ])>
                                    @if ($previousPlan)
                                        <x-filament::icon icon="heroicon-m-arrow-trending-up" class="subscription-page__plan-context-icon" />
                                        {{ __('fields.plan_upgrade_from', ['plan' => $previousPlan->name]) }}
                                    @else
                                        <x-filament::icon icon="heroicon-m-sparkles" class="subscription-page__plan-context-icon" />
                                        {{ __('fields.plan_starter_baseline') }}
                                    @endif
                                </p>

                                <div class="subscription-page__feature-sections">
                                    <div class="subscription-page__modules-panel">
                                        @foreach ($featureSections['groups'] as $group)
                                            <div class="subscription-page__module-block">
                                                @if ($group['compact'])
                                                    <div class="subscription-page__module-row">
                                                        <span class="subscription-page__module-row-label">{{ $group['label'] }}</span>

                                                        <span @class([
                                                            'subscription-page__module-row-badge',
                                                            'subscription-page__module-row-badge--' . $group['status'],
                                                        ])>
                                                            {{ $group['display'] }}
                                                        </span>
                                                    </div>

                                                    @if (! empty($group['modules']))
                                                        <div class="subscription-page__module-sublist" aria-label="{{ $group['label'] }}">
                                                            @foreach ($group['modules'] as $module)
                                                                <span class="subscription-page__module-chip">{{ $module }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="subscription-page__module-group-label">{{ $group['label'] }}</span>

                                                    <div class="subscription-page__module-subrows">
                                                        @foreach ($group['items'] as $item)
                                                            <div class="subscription-page__module-subrow">
                                                                <span class="subscription-page__module-subrow-label">{{ $item['label'] }}</span>

                                                                <span @class([
                                                                    'subscription-page__module-row-badge',
                                                                    'subscription-page__module-row-badge--' . $item['status'],
                                                                ])>
                                                                    {{ $item['display'] }}
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach

                                        @foreach ($featureSections['extras'] as $feature)
                                            <div class="subscription-page__module-block">
                                                <div class="subscription-page__module-row">
                                                    <span class="subscription-page__module-row-label">{{ $feature['label'] }}</span>

                                                    <span @class([
                                                        'subscription-page__module-row-badge',
                                                        'subscription-page__module-row-badge--' . $feature['status'],
                                                    ])>
                                                        {{ $feature['display'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                @if ($isSelected && ! $isCurrent)
                                    <span class="subscription-page__selected-badge">
                                        <x-filament::icon icon="heroicon-m-cursor-arrow-rays" class="subscription-page__status-badge-icon" />
                                        {{ __('fields.subscription_plan_selected') }}
                                    </span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($plansList->isNotEmpty())
            @php
                $selectedPlan = $plansList->firstWhere('id', $selectedPlanId);
                $selectedQuote = $selectedPlan ? $this->planQuote($selectedPlan) : null;
                $pricingService = \App\Services\SubscriptionPricingService::instance();
                $selectedVat = $selectedQuote
                    ? rtrim(rtrim(number_format($selectedQuote['tax_percent'], 2, '.', ''), '0'), '.')
                    : null;
            @endphp

            @if ($selectedPlan && $selectedQuote)
                <div @class([
                    'subscription-page__quote-panel',
                    'subscription-page__quote-panel--' . $this->planTier($selectedPlan),
                ])>
                    <div class="subscription-page__quote-panel-head">
                        <div>
                            <p class="subscription-page__quote-eyebrow">{{ __('fields.subscription_quote_summary_title') }}</p>
                            <h3 class="subscription-page__quote-plan">{{ $selectedPlan->name }}</h3>
                            <p class="subscription-page__quote-period">
                                {{ $billingPeriod === 'yearly' ? __('fields.yearly') : __('fields.monthly') }}
                                · {{ __('fields.subscription_prices_ex_tax_note') }}
                            </p>
                        </div>

                        <div class="subscription-page__quote-total">
                            @if ($selectedQuote['is_free'])
                                <strong>{{ __('fields.free') }}</strong>
                            @else
                                <strong>{{ $pricingService->formatMoney($selectedQuote['total_inc_tax'], $selectedQuote['currency']) }}</strong>
                                <span>{{ $this->planPriceSuffix($selectedPlan) }}</span>
                            @endif
                        </div>
                    </div>

                    @unless ($selectedQuote['is_free'] && empty($selectedQuote['discount_amount']))
                        <div class="subscription-page__price-stack subscription-page__price-stack--panel" aria-label="{{ __('fields.subscription_total_inc_tax') }}">
                            @if (! empty($selectedQuote['discount_amount']) && ($selectedQuote['discount_amount'] ?? 0) > 0)
                                <div class="subscription-page__price-row">
                                    <span>{{ __('fields.subscription_price_ex_tax_label') }}</span>
                                    <strong>{{ $pricingService->formatMoney($selectedQuote['subtotal_before_discount'] ?? ($selectedQuote['subtotal_ex_tax'] + $selectedQuote['discount_amount']), $selectedQuote['currency']) }}</strong>
                                </div>
                                <div class="subscription-page__price-row subscription-page__price-row--discount">
                                    <span>{{ __('fields.subscription_coupon_discount_label') }}@if (! empty($selectedQuote['coupon_code'])) ({{ $selectedQuote['coupon_code'] }})@endif</span>
                                    <strong>− {{ $pricingService->formatMoney($selectedQuote['discount_amount'], $selectedQuote['currency']) }}</strong>
                                </div>
                                <div class="subscription-page__price-row">
                                    <span>{{ __('fields.subscription_coupon_after_discount_label') }}</span>
                                    <strong>{{ $pricingService->formatMoney($selectedQuote['subtotal_ex_tax'], $selectedQuote['currency']) }}</strong>
                                </div>
                            @else
                                <div class="subscription-page__price-row">
                                    <span>{{ __('fields.subscription_price_ex_tax_label') }}</span>
                                    <strong>{{ $pricingService->formatMoney($selectedQuote['subtotal_ex_tax'], $selectedQuote['currency']) }}</strong>
                                </div>
                            @endif
                            <div class="subscription-page__price-row">
                                <span>{{ __('fields.subscription_price_tax_label') }} {{ $selectedVat }}%</span>
                                <strong>{{ $pricingService->formatMoney($selectedQuote['tax_amount'], $selectedQuote['currency']) }}</strong>
                            </div>
                            <div class="subscription-page__price-row subscription-page__price-row--total">
                                <span>{{ __('fields.subscription_total_inc_tax') }}</span>
                                <strong>{{ $pricingService->formatMoney($selectedQuote['total_inc_tax'], $selectedQuote['currency']) }}</strong>
                            </div>
                        </div>

                        @if ($billingPeriod === 'yearly')
                            <div class="subscription-page__yearly-chip">
                                <span>{{ __('fields.subscription_yearly_paid_months_label', ['months' => $selectedQuote['months']]) }}</span>
                                <span class="subscription-page__yearly-chip-sep" aria-hidden="true"></span>
                                <span>{{ __('fields.subscription_yearly_free_months_label', ['months' => $selectedQuote['discount_months']]) }}</span>
                            </div>
                        @endif
                    @endunless

                    @if ($this->hasActiveCoupons)
                        <div class="subscription-page__coupon">
                            <label class="subscription-page__coupon-label" for="subscription-coupon-code">
                                {{ __('fields.subscription_coupon_code_label') }}
                            </label>

                            <div class="subscription-page__coupon-row">
                                <input
                                    id="subscription-coupon-code"
                                    type="text"
                                    class="subscription-page__coupon-input"
                                    wire:model="couponCode"
                                    wire:keydown.enter.prevent="applyCoupon"
                                    placeholder="{{ __('fields.subscription_coupon_code_placeholder') }}"
                                    @disabled($appliedCouponId)
                                    autocomplete="off"
                                />

                                @if ($appliedCouponId)
                                    <x-filament::button
                                        type="button"
                                        color="gray"
                                        wire:click="clearAppliedCoupon"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('fields.subscription_coupon_remove') }}
                                    </x-filament::button>
                                @else
                                    <x-filament::button
                                        type="button"
                                        wire:click="applyCoupon"
                                        wire:loading.attr="disabled"
                                    >
                                        {{ __('fields.subscription_coupon_apply') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            <footer class="subscription-page__card-footer">
                @if ($selectedPlan && ! $this->isCurrentSelection($selectedPlan))
                    <p class="subscription-page__selected-summary subscription-page__selected-summary--{{ $this->planTier($selectedPlan) }}">
                        <x-filament::icon icon="heroicon-m-check-circle" class="subscription-page__selected-summary-icon" />
                        <span>
                            {{ __('fields.subscription_selected_plan_summary', ['plan' => $selectedPlan->name]) }}
                        </span>
                    </p>
                @endif

                <x-filament::button
                    type="button"
                    wire:click="openConfirmModal"
                    wire:loading.attr="disabled"
                    class="w-full"
                    :disabled="$this->isCurrentSelection($selectedPlan)"
                >
                    {{ __('fields.subscription_update_plan') }}
                </x-filament::button>
            </footer>
        @endif
    </section>

    @if ($showConfirmModal && ($change = $this->planChangeSummary))
        <div
            class="subscription-confirm"
            wire:keydown.escape.window="closeConfirmModal"
        >
            <button
                type="button"
                class="subscription-confirm__backdrop"
                wire:click="closeConfirmModal"
                aria-label="{{ __('fields.subscription_confirm_cancel') }}"
            ></button>

            <div
                class="subscription-confirm__dialog subscription-confirm__dialog--{{ $change['direction'] }} subscription-confirm__dialog--tier-{{ $change['to_tier'] }}"
                role="dialog"
                aria-modal="true"
                aria-labelledby="subscription-confirm-title"
            >
                <div class="subscription-confirm__glow" aria-hidden="true"></div>

                <header class="subscription-confirm__header">
                    <div class="subscription-confirm__icon-wrap subscription-confirm__icon-wrap--{{ $change['direction'] }}">
                        @if ($change['direction'] === 'upgrade')
                            <x-filament::icon icon="heroicon-m-arrow-trending-up" class="subscription-confirm__icon" />
                        @elseif ($change['direction'] === 'downgrade')
                            <x-filament::icon icon="heroicon-m-arrow-trending-down" class="subscription-confirm__icon" />
                        @else
                            <x-filament::icon icon="heroicon-m-arrows-right-left" class="subscription-confirm__icon" />
                        @endif
                    </div>

                    <div class="subscription-confirm__heading">
                        <span @class([
                            'subscription-confirm__badge',
                            'subscription-confirm__badge--upgrade' => $change['direction'] === 'upgrade',
                            'subscription-confirm__badge--downgrade' => $change['direction'] === 'downgrade',
                            'subscription-confirm__badge--lateral' => $change['direction'] === 'lateral',
                        ])>
                            {{ __('fields.subscription_confirm_' . $change['direction'] . '_badge') }}
                        </span>

                        <h3 id="subscription-confirm-title" class="subscription-confirm__title">
                            {{ __('fields.subscription_confirm_title') }}
                        </h3>
                        <p class="subscription-confirm__subtitle">{{ __('fields.subscription_confirm_subtitle') }}</p>
                    </div>

                    <button type="button" class="subscription-confirm__close" wire:click="closeConfirmModal">
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </header>

                <div class="subscription-confirm__body">
                    <p class="subscription-confirm__question">{{ __('fields.subscription_confirm_question') }}</p>

                    <div class="subscription-confirm__transition">
                        <div class="subscription-confirm__plan subscription-confirm__plan--from subscription-confirm__plan--tier-{{ $change['from_tier'] }}">
                            <span class="subscription-confirm__plan-label">{{ __('fields.subscription_current_plan') }}</span>
                            <strong>{{ $change['from']->name }}</strong>
                        </div>

                        <div class="subscription-confirm__arrow" aria-hidden="true">
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-5 w-5" />
                        </div>

                        <div class="subscription-confirm__plan subscription-confirm__plan--to subscription-confirm__plan--tier-{{ $change['to_tier'] }}">
                            <span class="subscription-confirm__plan-label">{{ __('fields.subscription_plan_selected') }}</span>
                            <strong>{{ $change['to']->name }}</strong>
                        </div>
                    </div>

                    @if ($change['price_change'])
                        <div class="subscription-confirm__price">
                            <span>{{ $change['price_change']['label'] }}</span>
                            <strong>{{ $change['price_change']['value'] }}</strong>
                        </div>
                    @endif

                    <div class="subscription-confirm__sections">
                        <section class="subscription-confirm__section subscription-confirm__section--gains">
                            <h4 class="subscription-confirm__section-title">
                                <x-filament::icon icon="heroicon-m-sparkles" class="subscription-confirm__section-icon" />
                                {{ __('fields.subscription_confirm_gains_title') }}
                            </h4>

                            @if (count($change['gains']) > 0)
                                <ul class="subscription-confirm__list">
                                    @foreach ($change['gains'] as $item)
                                        <li class="subscription-confirm__list-item subscription-confirm__list-item--gain">
                                            <x-filament::icon icon="heroicon-m-check-circle" class="subscription-confirm__list-icon" />
                                            <div>
                                                <span class="subscription-confirm__list-label">{{ $item['label'] }}</span>
                                                <span class="subscription-confirm__list-detail">{{ $item['detail'] }}</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="subscription-confirm__empty">{{ __('fields.subscription_confirm_gains_empty') }}</p>
                            @endif
                        </section>

                        <section class="subscription-confirm__section subscription-confirm__section--losses">
                            <h4 class="subscription-confirm__section-title">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="subscription-confirm__section-icon" />
                                {{ __('fields.subscription_confirm_losses_title') }}
                            </h4>

                            @if (count($change['losses']) > 0)
                                <ul class="subscription-confirm__list">
                                    @foreach ($change['losses'] as $item)
                                        <li class="subscription-confirm__list-item subscription-confirm__list-item--loss">
                                            <x-filament::icon icon="heroicon-m-minus-circle" class="subscription-confirm__list-icon" />
                                            <div>
                                                <span class="subscription-confirm__list-label">{{ $item['label'] }}</span>
                                                <span class="subscription-confirm__list-detail">{{ $item['detail'] }}</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="subscription-confirm__empty">{{ __('fields.subscription_confirm_losses_empty') }}</p>
                            @endif
                        </section>
                    </div>
                </div>

                <footer class="subscription-confirm__footer">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="closeConfirmModal"
                        class="subscription-confirm__btn-cancel"
                    >
                        {{ __('fields.subscription_confirm_cancel') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="confirmUpdateSubscription"
                        wire:loading.attr="disabled"
                        class="subscription-confirm__btn-confirm"
                    >
                        <span wire:loading.remove wire:target="confirmUpdateSubscription">
                            {{ __('fields.subscription_confirm_proceed') }}
                        </span>
                        <span wire:loading wire:target="confirmUpdateSubscription">
                            {{ __('fields.subscription_update_plan') }}...
                        </span>
                    </x-filament::button>
                </footer>
            </div>
        </div>
    @endif
</div>
