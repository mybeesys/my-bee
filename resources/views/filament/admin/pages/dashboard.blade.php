@php
    $user = filament()->auth()->user();
    $isAr = app()->getLocale() === 'ar';
@endphp

<x-filament-panels::page class="fi-dashboard-page fi-admin-dashboard">
    <div class="dashboard-hero mb-6">
        <div class="dashboard-hero__glow" aria-hidden="true"></div>
        <div class="dashboard-hero__pattern" aria-hidden="true"></div>
        <div class="dashboard-hero__content">
            <div class="dashboard-hero__text {{ $isAr ? 'text-right' : 'text-left' }}">
                <p class="dashboard-hero__eyebrow">{{ __('fields.admin_dashboard_eyebrow') }}</p>
                <h1 class="dashboard-hero__title">
                    {{ __('fields.admin_dashboard_welcome', ['name' => $user?->full_name ?? $user?->name ?? '']) }}
                </h1>
                <p class="dashboard-hero__subtitle">
                    {{ __('fields.admin_dashboard_subtitle', ['date' => now()->translatedFormat('l، d F Y')]) }}
                </p>
            </div>
            <div class="dashboard-hero__actions">
                @foreach ($this->getQuickLinks() as $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="dashboard-hero__chip"
                        wire:navigate
                    >
                        <x-filament::icon
                            :icon="$link['icon']"
                            class="h-4 w-4 shrink-0 opacity-80"
                        />
                        <span>{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleWidgets()"
        class="dashboard-widgets"
    />
</x-filament-panels::page>
