@php
    $logoUrl = filament()->getBrandLogo();
@endphp

<div class="tenant-login-page" dir="{{ __('filament-panels::layout.direction') }}">
    <div class="tenant-login-card">
        <div class="tenant-login-card__accent" aria-hidden="true"></div>

        <div class="tenant-login-card__grid">
            {{-- Form column (right in RTL) --}}
            <div class="tenant-login-card__form">
                <p class="tenant-login-card__eyebrow">
                    {{ __('fields.login_company_heading') }}
                </p>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

                <x-filament-panels::form id="form" wire:submit="authenticate" class="tenant-login-form">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                        class="tenant-login-form-actions"
                    />
                </x-filament-panels::form>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
            </div>

            {{-- Brand column (left in RTL) --}}
            <div class="tenant-login-card__brand">
                <div class="tenant-login-brand__inner">
                    @if ($logoUrl)
                        <img
                            src="{{ $logoUrl }}"
                            alt="{{ filament()->getBrandName() }}"
                            class="tenant-login-brand__logo"
                        />
                    @endif

                    <p class="tenant-login-brand__name">{{ config('app.name', 'MY BEE') }}</p>

                    <h2 class="tenant-login-brand__title">
                        {{ __('fields.login_welcome_title') }}
                    </h2>

                    <p class="tenant-login-brand__text">
                        {{ __('fields.login_welcome_text') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
