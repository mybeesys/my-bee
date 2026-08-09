<div class="choose-subscription-page register-activity-page fi-simple-page">
    <div class="fi-simple-header mb-6 text-center">
        <h1 class="fi-simple-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
            {{ __('fields.choose_subscription_title') }}
        </h1>
        <p class="fi-simple-header-subheading mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ __('fields.choose_subscription_subheading') }}
        </p>
    </div>

    <div class="choose-subscription-intro">
        <p class="register-activity-intro__text">
            {{ __('fields.choose_subscription_intro') }}
        </p>
    </div>

    <div class="choose-subscription-card">
        <livewire:manage-subscription :onboarding="true" />
    </div>
</div>
