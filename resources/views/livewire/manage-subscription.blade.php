<div class="subscription-page">
    @php
        $subscription = $this->currentSubscription;
        $currentPlan = $this->currentPlan;
        $currency = main_currency_iso_code();
        $nextBilling = $this->nextBillingDate();
    @endphp

    @if ($currentPlan && $subscription)
        <section class="subscription-page__card subscription-page__card--current">
            <header class="subscription-page__card-header">
                <div>
                    <p class="subscription-page__eyebrow">{{ __('fields.subscription_current_plan') }}</p>
                    <h2 class="subscription-page__title">{{ $currentPlan->name }}</h2>
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
                        <dd>{{ $this->planSpanLabel($currentPlan) }}</dd>
                    </div>
                </dl>

                <div class="subscription-page__price">
                    <span class="subscription-page__price-value">
                        {{ $currency }} {{ format_amount($subscription->price ?? $currentPlan->price) }}
                    </span>
                    @if ($suffix = $this->planPriceSuffix($currentPlan))
                        <span class="subscription-page__price-suffix">{{ $suffix }}</span>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="subscription-page__card">
        <header class="subscription-page__card-header">
            <div>
                <p class="subscription-page__eyebrow">{{ __('fields.subscription_change_plan') }}</p>
                <h2 class="subscription-page__title">{{ __('fields.subscription_plans') }}</h2>
                <p class="subscription-page__subtitle">{{ __('fields.subscription_change_plan_hint') }}</p>
            </div>
        </header>

        <div class="subscription-page__card-body">
            @if ($this->plans->isEmpty())
                <p class="subscription-page__empty">{{ __('fields.subscription_no_plans') }}</p>
            @else
                <div class="subscription-page__plans">
                    @foreach ($this->plans as $plan)
                        @php
                            $isCurrent = $currentPlan?->id === $plan->id;
                            $isSelected = $selectedPlanId === $plan->id;
                        @endphp

                        <label @class([
                            'subscription-page__plan',
                            'subscription-page__plan--selected' => $isSelected,
                            'subscription-page__plan--current' => $isCurrent,
                        ])>
                            <input
                                type="radio"
                                name="selectedPlanId"
                                value="{{ $plan->id }}"
                                wire:model.live="selectedPlanId"
                                class="sr-only"
                            />

                            <div class="subscription-page__plan-inner">
                                <div class="subscription-page__plan-head">
                                    <div>
                                        <h3 class="subscription-page__plan-name">{{ $plan->name }}</h3>
                                        <p class="subscription-page__plan-span">{{ $this->planSpanLabel($plan) }}</p>
                                    </div>
                                    <div class="subscription-page__plan-price">
                                        <span>{{ $currency }} {{ format_amount($plan->price) }}</span>
                                        @if ($suffix = $this->planPriceSuffix($plan))
                                            <small>{{ $suffix }}</small>
                                        @endif
                                    </div>
                                </div>

                                <ul class="subscription-page__features">
                                    @foreach ($this->planFeatures($plan) as $feature)
                                        <li>
                                            <x-filament::icon icon="heroicon-m-check" class="subscription-page__check" />
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($isCurrent)
                                    <span class="subscription-page__plan-tag">{{ __('fields.subscription_your_plan') }}</span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($this->plans->isNotEmpty())
            <footer class="subscription-page__card-footer">
                <x-filament::button
                    type="button"
                    wire:click="updateSubscription"
                    wire:confirm="{{ __('fields.subscription_update_confirm') }}"
                    wire:loading.attr="disabled"
                    class="w-full"
                    :disabled="$selectedPlanId === $currentPlan?->id"
                >
                    {{ __('fields.subscription_update_plan') }}
                </x-filament::button>
            </footer>
        @endif
    </section>
</div>
