@php
    use App\Filament\Tenant\Pages\Subscription;
    use App\Models\User;
    use Filament\Facades\Filament;

    $tenantPanel = Filament::getCurrentPanel()?->getId() === 'tenant';
    $isClientOwner = auth()->user()?->hasRole(User::ROLE_CLIENT) ?? false;
    $hasStore = false;
    $storePlan = null;

    if ($tenantPanel && $isClientOwner) {
        try {
            $hasStore = plan_allows_store();
            $storePlan = subscription_store_plan();
        } catch (\Throwable) {
            $hasStore = false;
            $storePlan = null;
        }
    }

    $subscriptionUrl = $tenantPanel ? Subscription::getUrl() : '#';
    $shopUrl = $tenantPanel && Filament::getTenant()
        ? config('app.shop_url') . Filament::getTenant()->slug
        : '#';
@endphp

@if ($tenantPanel && $isClientOwner)
    <div
        x-data="{ open: false }"
        class="shop-nav-link"
    >
        @if ($hasStore)
            <a
                href="{{ $shopUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="shop-nav-link__anchor"
            >
                {{ __('fields.shop_link') }}
            </a>
        @else
            <button
                type="button"
                class="shop-nav-link__anchor shop-nav-link__anchor--locked"
                x-on:click="open = true"
            >
                <x-filament::icon icon="heroicon-m-shopping-bag" class="shop-nav-link__lock-icon" />
                {{ __('fields.shop_link') }}
            </button>

            <template x-teleport="body">
                <div
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    class="store-upgrade"
                    x-on:keydown.escape.window="open = false"
                >
                    <button
                        type="button"
                        class="store-upgrade__backdrop"
                        x-on:click="open = false"
                        aria-label="{{ __('fields.subscription_confirm_cancel') }}"
                    ></button>

                    <div
                        class="store-upgrade__dialog"
                        role="dialog"
                        aria-modal="true"
                        x-on:click.outside="open = false"
                    >
                        <div class="store-upgrade__glow" aria-hidden="true"></div>

                        <header class="store-upgrade__header">
                            <div class="store-upgrade__icon-wrap">
                                <x-filament::icon icon="heroicon-m-shopping-bag" class="store-upgrade__icon" />
                            </div>

                            <div class="store-upgrade__heading">
                                <span class="store-upgrade__badge">{{ __('fields.store_upgrade_badge') }}</span>
                                <h3 class="store-upgrade__title">{{ __('fields.store_upgrade_title') }}</h3>
                                <p class="store-upgrade__subtitle">
                                    {{ __('fields.store_upgrade_subtitle', ['plan' => $storePlan?->name ?? __('fields.plan_tier_premium')]) }}
                                </p>
                            </div>

                            <button type="button" class="store-upgrade__close" x-on:click="open = false">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                            </button>
                        </header>

                        <div class="store-upgrade__body">
                            <p class="store-upgrade__lead">{{ __('fields.store_upgrade_lead') }}</p>

                            <ul class="store-upgrade__features">
                                <li>
                                    <x-filament::icon icon="heroicon-m-globe-alt" class="store-upgrade__feature-icon" />
                                    <div>
                                        <strong>{{ __('fields.store_upgrade_feature_store_title') }}</strong>
                                        <span>{{ __('fields.store_upgrade_feature_store_body') }}</span>
                                    </div>
                                </li>
                                <li>
                                    <x-filament::icon icon="heroicon-m-credit-card" class="store-upgrade__feature-icon" />
                                    <div>
                                        <strong>{{ __('fields.store_upgrade_feature_payments_title') }}</strong>
                                        <span>{{ __('fields.store_upgrade_feature_payments_body') }}</span>
                                    </div>
                                </li>
                                <li>
                                    <x-filament::icon icon="heroicon-m-truck" class="store-upgrade__feature-icon" />
                                    <div>
                                        <strong>{{ __('fields.store_upgrade_feature_orders_title') }}</strong>
                                        <span>{{ __('fields.store_upgrade_feature_orders_body') }}</span>
                                    </div>
                                </li>
                                <li>
                                    <x-filament::icon icon="heroicon-m-user-group" class="store-upgrade__feature-icon" />
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
                                <div class="store-upgrade__plan">
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
                        </div>

                        <footer class="store-upgrade__footer">
                            <x-filament::button
                                type="button"
                                color="gray"
                                tag="button"
                                x-on:click="open = false"
                            >
                                {{ __('fields.subscription_confirm_cancel') }}
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                tag="a"
                                href="{{ $subscriptionUrl }}"
                            >
                                {{ __('fields.store_upgrade_cta', ['plan' => $storePlan?->name ?? __('fields.plan_tier_premium')]) }}
                            </x-filament::button>
                        </footer>
                    </div>
                </div>
            </template>
        @endif
    </div>
@endif
