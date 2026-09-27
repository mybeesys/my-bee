@php
    $plan = $this->getPendingPlan();
    $quote = $this->getQuote();
    $pricing = \App\Services\SubscriptionPricingService::instance();
    $period = $this->getBillingPeriod();
    $totalLabel = $plan && $quote
        ? $pricing->formatMoney($quote['total_inc_tax'], $quote['currency'])
        : null;
@endphp

<div class="choose-subscription-page choose-registration-plan-page register-activity-page fi-simple-page complete-registration-payment-page">
    @include('filament.tenant.components.registration-steps', ['currentStep' => 2])

    <div class="fi-simple-header mb-2 text-center">
        <p class="complete-pay-page__eyebrow">{{ __('fields.registration_pay_eyebrow') }}</p>
        <h1 class="complete-pay-page__title">{{ __('fields.registration_pay_title') }}</h1>
        <p class="complete-pay-page__sub">{{ __('fields.registration_pay_subheading') }}</p>
    </div>

    @if ($plan && $quote)
        <div class="complete-pay">
            <section class="complete-pay__panel" aria-labelledby="complete-pay-billing-title">
                @unless ($this->hyperPayConfigured)
                    <div class="complete-pay__alert">
                        {{ __('fields.hyperpay_not_configured') }}
                    </div>
                @endunless

                <h2 id="complete-pay-billing-title" class="complete-pay__panel-title">
                    {{ __('fields.hyperpay_billing_short') }}
                </h2>
                <p class="complete-pay__hint">{{ __('fields.hyperpay_billing_hint') }}</p>

                <div class="complete-pay__grid">
                    <label class="complete-pay__field">
                        <span>{{ __('fields.hyperpay_given_name') }}</span>
                        <input type="text" wire:model="billingGivenName" autocomplete="given-name">
                    </label>
                    <label class="complete-pay__field">
                        <span>{{ __('fields.hyperpay_surname') }}</span>
                        <input type="text" wire:model="billingSurname" autocomplete="family-name">
                    </label>
                    <label class="complete-pay__field complete-pay__span">
                        <span>{{ __('fields.email') }}</span>
                        <input type="email" wire:model="billingEmail" autocomplete="email">
                    </label>
                    <label class="complete-pay__field complete-pay__span">
                        <span>{{ __('fields.hyperpay_street') }}</span>
                        <input type="text" wire:model="billingStreet1" autocomplete="street-address" placeholder="{{ __('fields.hyperpay_street_ph') }}">
                    </label>
                    <label class="complete-pay__field">
                        <span>{{ __('fields.city') }}</span>
                        <input type="text" wire:model="billingCity" autocomplete="address-level2" placeholder="{{ __('fields.hyperpay_city_ph') }}">
                    </label>
                    <label class="complete-pay__field">
                        <span>{{ __('fields.hyperpay_state') }}</span>
                        <input type="text" wire:model="billingState" autocomplete="address-level1" placeholder="{{ __('fields.hyperpay_state_ph') }}">
                    </label>
                    <label class="complete-pay__field complete-pay__field--select">
                        <span>{{ __('fields.country') }}</span>
                        <select wire:model="billingCountry">
                            @foreach ($this->billingCountryOptions as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="complete-pay__field">
                        <span>{{ __('fields.hyperpay_postcode') }}</span>
                        <input type="text" wire:model="billingPostcode" autocomplete="postal-code" inputmode="numeric" maxlength="5" placeholder="{{ __('fields.hyperpay_postcode_ph') }}">
                    </label>
                </div>
            </section>

            <aside class="complete-pay__panel complete-pay__aside" aria-label="{{ __('fields.hyperpay_order_title') }}">
                <div class="complete-pay__summary-row">
                    <span class="complete-pay__plan-label">{{ __('fields.hyperpay_order_title') }}</span>
                    <a href="{{ \App\Filament\Tenant\Pages\ChooseRegistrationPlan::getUrl() }}" class="complete-pay__change">
                        {{ __('fields.registration_change_plan') }}
                    </a>
                </div>
                <div class="complete-pay__divider" aria-hidden="true"></div>

                <span class="complete-pay__plan-tag">
                    {{ $plan->name }} — {{ $period === 'yearly' ? __('fields.yearly') : __('fields.monthly') }}
                </span>

                <div class="complete-pay__total">
                    <strong>{{ $totalLabel }}</strong>
                    <span>{{ __('fields.subscription_price_incl_tax_hint') }}</span>
                </div>

                <div class="complete-pay__brands" aria-label="{{ __('fields.hyperpay_pay_with_card') }}">
                    @foreach ($this->paymentBrands as $brand)
                        @php $brandKey = strtoupper($brand); @endphp
                        <span class="complete-pay__brand complete-pay__brand--{{ strtolower($brandKey) }}">
                            @if ($brandKey === 'MADA')
                                <svg viewBox="0 0 52 18" width="46" height="16" aria-hidden="true">
                                    <rect width="52" height="18" rx="3" fill="#00A651"/>
                                    <text x="26" y="13" text-anchor="middle" fill="#fff" font-size="9" font-family="Inter,Arial" font-weight="800">mada</text>
                                </svg>
                            @elseif ($brandKey === 'VISA')
                                <svg viewBox="0 0 44 18" width="40" height="16" aria-hidden="true">
                                    <rect width="44" height="18" rx="3" fill="#1A1F71"/>
                                    <text x="22" y="13" text-anchor="middle" fill="#fff" font-size="8" font-family="Inter,Arial" font-weight="800">VISA</text>
                                </svg>
                            @elseif ($brandKey === 'MASTER')
                                <svg viewBox="0 0 36 18" width="32" height="16" aria-hidden="true">
                                    <rect width="36" height="18" rx="3" fill="#111827"/>
                                    <circle cx="14" cy="9" r="6" fill="#EB001B"/>
                                    <circle cx="22" cy="9" r="6" fill="#F79E1B"/>
                                </svg>
                            @else
                                {{ $brand }}
                            @endif
                        </span>
                    @endforeach
                </div>

                <button
                    type="button"
                    wire:click="pay"
                    wire:loading.attr="disabled"
                    class="complete-pay__cta"
                    @disabled(! $this->hyperPayConfigured)
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="5" y="11" width="14" height="10" rx="2"/>
                        <path d="M8 11V8a4 4 0 0 1 8 0v3"/>
                    </svg>
                    <span wire:loading.remove wire:target="pay">{{ __('fields.hyperpay_continue_to_payment') }}</span>
                    <span wire:loading wire:target="pay">{{ __('fields.please_wait') }}</span>
                </button>

                <p class="complete-pay__secure">{{ __('fields.hyperpay_secure_lock') }}</p>
                <p class="complete-pay__note">{{ __('fields.hyperpay_checkout_note') }}</p>
            </aside>
        </div>
    @endif
</div>
