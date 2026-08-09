@php
    $currentStep = (int) ($currentStep ?? 1);
@endphp

<nav class="registration-steps" aria-label="{{ __('fields.registration_steps_label') }}">
    <ol class="registration-steps__list">
        <li @class([
            'registration-steps__item',
            'registration-steps__item--active' => $currentStep === 1,
            'registration-steps__item--completed' => $currentStep > 1,
        ])>
            <span class="registration-steps__marker" aria-hidden="true">1</span>
            <span class="registration-steps__label">{{ __('fields.registration_step_choose_plan') }}</span>
        </li>

        <li class="registration-steps__connector" aria-hidden="true"></li>

        <li @class([
            'registration-steps__item',
            'registration-steps__item--active' => $currentStep === 2,
        ])>
            <span class="registration-steps__marker" aria-hidden="true">2</span>
            <span class="registration-steps__label">{{ __('fields.registration_step_create_account') }}</span>
        </li>
    </ol>
</nav>
