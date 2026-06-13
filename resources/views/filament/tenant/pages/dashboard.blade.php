@php
    $tenant = filament()->getTenant();
    $user = filament()->auth()->user();
    $isAr = app()->getLocale() === 'ar';
@endphp

<x-filament-panels::page class="fi-dashboard-page">
    <div class="dashboard-hero mb-6">
        <div class="dashboard-hero__glow" aria-hidden="true"></div>
        <div class="dashboard-hero__content">
            <div class="dashboard-hero__text {{ $isAr ? 'text-right' : 'text-left' }}">
                <p class="dashboard-hero__eyebrow">{{ __('fields.dashboard_welcome_eyebrow') }}</p>
                <h1 class="dashboard-hero__title">
                    {{ __('fields.dashboard_welcome', ['name' => $user?->full_name ?? $user?->name ?? '']) }}
                </h1>
                <p class="dashboard-hero__subtitle">
                    {{ $tenant?->name }}
                    <span class="dashboard-hero__dot" aria-hidden="true">·</span>
                    {{ now()->translatedFormat('l، d F Y') }}
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

    @if (method_exists($this, 'filtersForm'))
        {{ $this->filtersForm }}
    @endif

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="
            [
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                ...$this->getWidgetData(),
            ]
        "
        :widgets="$this->getVisibleWidgets()"
        class="dashboard-widgets"
    />
</x-filament-panels::page>
