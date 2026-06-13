@php
    $limitReached = $this->hasReachedActivitiesLimit();
@endphp

<x-filament-panels::page.simple class="register-activity-page">
    @unless ($limitReached)
        <div class="register-activity-intro" aria-hidden="false">
            <p class="register-activity-intro__text">
                {{ __('fields.register_activity_intro') }}
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
</x-filament-panels::page.simple>
