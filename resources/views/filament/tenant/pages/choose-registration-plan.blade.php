<div class="choose-subscription-page choose-registration-plan-page register-activity-page fi-simple-page">
    @include('filament.tenant.components.registration-steps', ['currentStep' => 1])

    <div class="fi-simple-header mb-6 text-center">
        <h1 class="fi-simple-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
            {{ __('fields.registration_choose_plan_title') }}
        </h1>
    </div>

    <div class="choose-subscription-card">
        <livewire:manage-subscription :registration-flow="true" wire:key="registration-plan-picker" />
    </div>

    <p class="register-activity-login-hint">
        {{ __('fields.register_have_account_prompt') }}
        <a href="{{ filament()->getLoginUrl() }}" wire:navigate class="register-activity-login-hint__link">
            {{ __('fields.register_back_to_login') }}
        </a>
    </p>
</div>
