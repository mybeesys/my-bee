@php
    $limitReached = Filament\Facades\Filament::auth()->check() && $this->hasReachedActivitiesLimit();
@endphp

<x-filament-panels::page.simple class="register-activity-page">
    @unless ($limitReached)
        @if (! Filament\Facades\Filament::auth()->check())
            @include('filament.tenant.components.registration-steps', ['currentStep' => 2])
            @include('filament.tenant.components.registration-plan-summary')
        @endif

        <div class="register-activity-intro" aria-hidden="false">
            <p class="register-activity-intro__text">
                {{ Filament\Facades\Filament::auth()->check()
                    ? __('fields.register_activity_intro')
                    : __('fields.join_activity_intro') }}
            </p>
        </div>
    @endunless

    <div @class([
        'register-activity-card',
        'register-activity-card--limit' => $limitReached,
    ])>
        <x-filament-panels::form id="form" wire:submit="register">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
                class="register-activity-form-actions"
            />
        </x-filament-panels::form>
    </div>

    @if (! Filament\Facades\Filament::auth()->check())
        <p class="register-activity-login-hint">
            {{ __('fields.register_have_account_prompt') }}
            <a href="{{ filament()->getLoginUrl() }}" wire:navigate class="register-activity-login-hint__link">
                {{ __('fields.register_back_to_login') }}
            </a>
        </p>
    @endif
</x-filament-panels::page.simple>
