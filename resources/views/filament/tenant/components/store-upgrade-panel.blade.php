@php
    use App\Filament\Tenant\Pages\Subscription;

    $storePlan = subscription_store_plan();
    $subscriptionUrl = Subscription::getUrl();
    $context = $context ?? 'shop';
@endphp

<div class="store-upgrade-panel">
    <div class="store-upgrade-panel__card">
        <div class="store-upgrade-panel__glow" aria-hidden="true"></div>

        <header class="store-upgrade-panel__header">
            <div class="store-upgrade-panel__icon-wrap">
                <x-filament::icon
                    :icon="$context === 'orders' ? 'heroicon-o-shopping-cart' : 'heroicon-o-shopping-bag'"
                    class="store-upgrade-panel__icon"
                />
            </div>

            <div>
                <span class="store-upgrade-panel__badge">{{ __('fields.store_upgrade_badge') }}</span>
                <h2 class="store-upgrade-panel__title">
                    {{ $context === 'orders' ? __('fields.orders_store_upgrade_title') : __('fields.store_upgrade_title') }}
                </h2>
                <p class="store-upgrade-panel__subtitle">
                    {{ __('fields.store_upgrade_subtitle', ['plan' => $storePlan?->name ?? __('fields.plan_tier_premium')]) }}
                </p>
            </div>
        </header>

        <p class="store-upgrade-panel__lead">
            {{ $context === 'orders' ? __('fields.orders_store_upgrade_lead') : __('fields.store_upgrade_lead') }}
        </p>

        <ul class="store-upgrade-panel__features">
            <li>
                <x-filament::icon icon="heroicon-m-globe-alt" class="store-upgrade-panel__feature-icon" />
                <div>
                    <strong>{{ __('fields.store_upgrade_feature_store_title') }}</strong>
                    <span>{{ __('fields.store_upgrade_feature_store_body') }}</span>
                </div>
            </li>
            <li>
                <x-filament::icon icon="heroicon-m-shopping-cart" class="store-upgrade-panel__feature-icon" />
                <div>
                    <strong>{{ __('fields.store_upgrade_feature_orders_title') }}</strong>
                    <span>{{ __('fields.store_upgrade_feature_orders_body') }}</span>
                </div>
            </li>
            <li>
                <x-filament::icon icon="heroicon-m-credit-card" class="store-upgrade-panel__feature-icon" />
                <div>
                    <strong>{{ __('fields.store_upgrade_feature_payments_title') }}</strong>
                    <span>{{ __('fields.store_upgrade_feature_payments_body') }}</span>
                </div>
            </li>
            <li>
                <x-filament::icon icon="heroicon-m-user-group" class="store-upgrade-panel__feature-icon" />
                <div>
                    <strong>{{ __('fields.store_upgrade_feature_team_title') }}</strong>
                    <span>{{ __('fields.store_upgrade_feature_team_body') }}</span>
                </div>
            </li>
        </ul>

        @if ($storePlan)
            @php
                $storeQuote = subscription_pricing($storePlan, 'monthly');
                $pricing = \App\Services\SubscriptionPricingService::instance();
            @endphp
            <div class="store-upgrade-panel__plan">
                <span>{{ __('fields.store_upgrade_recommended_plan') }}</span>
                <strong>{{ $storePlan->name }}</strong>
                <small>
                    @if ($storeQuote['is_free'])
                        {{ __('fields.free') }}
                    @else
                        {{ $pricing->formatMoney($storeQuote['total_inc_tax'], $storeQuote['currency']) }}
                        {{ __('fields.subscription_per_month') }}
                    @endif
                </small>
            </div>
        @endif

        <div class="store-upgrade-panel__actions">
            <x-filament::button tag="a" href="{{ $subscriptionUrl }}">
                {{ __('fields.store_upgrade_cta', ['plan' => $storePlan?->name ?? __('fields.plan_tier_premium')]) }}
            </x-filament::button>
        </div>
    </div>
</div>
