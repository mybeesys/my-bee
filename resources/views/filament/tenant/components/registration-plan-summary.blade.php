@php
    $selection = registration_plan_selection();
    $plan = $selection ? \App\Models\Plan::query()->find($selection['plan_id']) : null;
    $billingPeriod = $selection
        ? \App\Services\SubscriptionPricingService::instance()->normalizeBillingPeriod($selection['billing_period'])
        : null;
@endphp

@if ($plan)
    @php
        $quote = subscription_pricing($plan, $billingPeriod);
        $pricingService = \App\Services\SubscriptionPricingService::instance();
    @endphp

    <aside class="registration-plan-summary" aria-label="{{ __('fields.registration_selected_plan_label') }}">
        <div class="registration-plan-summary__header">
            <span class="registration-plan-summary__eyebrow">{{ __('fields.registration_selected_plan_label') }}</span>
            <a
                href="{{ \App\Filament\Tenant\Pages\ChooseRegistrationPlan::getUrl(['from' => 'registration']) }}"
                wire:navigate
                class="registration-plan-summary__change"
            >
                {{ __('fields.registration_change_plan') }}
            </a>
        </div>

        <div class="registration-plan-summary__body">
            <h3 class="registration-plan-summary__name">{{ $plan->name }}</h3>
            <p class="registration-plan-summary__billing">
                {{ $billingPeriod === \App\Services\SubscriptionPricingService::BILLING_YEARLY
                    ? __('fields.yearly')
                    : __('fields.monthly') }}
            </p>
            <p class="registration-plan-summary__price">
                @if ($quote['is_free'])
                    {{ __('fields.free') }}
                @else
                    {{ $pricingService->formatMoney($quote['total_inc_tax'], $quote['currency']) }}
                @endif
            </p>
        </div>
    </aside>
@endif
