@php
    $logoUrl = filament()->getBrandLogo();
    $usesFullBrandLogo = filled($logoUrl)
        && $logoUrl === system_brand_logo_url()
        && system_brand_logo_url() !== system_logo_icon_url();
@endphp

<div class="tenant-login-page tenant-register-page" dir="{{ __('filament-panels::layout.direction') }}">
    <div class="tenant-login-card">
        <div class="tenant-login-card__accent" aria-hidden="true"></div>

        <div class="tenant-login-card__grid">
            <div class="tenant-login-card__form">
                <p class="tenant-login-card__eyebrow">
                    {{ __('fields.register_page_eyebrow') }}
                </p>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

                <x-filament-panels::form id="form" wire:submit="register" class="tenant-login-form">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                        class="tenant-login-form-actions"
                    />
                </x-filament-panels::form>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_REGISTER_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}

                @if (filament()->hasLogin())
                    <div class="tenant-login-register-cta tenant-login-register-cta--login">
                        <p class="tenant-login-register-cta__text">
                            {{ __('fields.register_have_account_prompt') }}
                        </p>
                        <a
                            href="{{ filament()->getLoginUrl() }}"
                            class="tenant-login-register-cta__link"
                            wire:navigate
                        >
                            {{ __('fields.register_back_to_login') }}
                        </a>
                    </div>
                @endif
            </div>

            <div class="tenant-login-card__brand">
                <div class="tenant-login-brand__inner">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ config('app.name', 'MY BEE') }}"
                            class="tenant-login-brand__logo"
                        />
                    @endif

                    @unless ($usesFullBrandLogo)
                        <p class="tenant-login-brand__name">{{ config('app.name', 'MY BEE') }}</p>
                    @endunless

                    <span class="tenant-login-brand__badge">
                        {{ __('fields.register_page_badge') }}
                    </span>

                    <h2 class="tenant-login-brand__title">
                        {{ __('fields.register_page_welcome_title') }}
                    </h2>

                    <p class="tenant-login-brand__text">
                        {{ __('fields.register_page_welcome_text') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
