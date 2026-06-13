@if (filament()->isSidebarCollapsibleOnDesktop() && filament()->hasNavigation() && ! filament()->hasTopNavigation())
    <div
        class="fi-topbar-collapsed-brand me-2 shrink-0 sm:me-4"
        x-cloak
        x-show="! $store.sidebar.isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    >
        @if ($homeUrl = filament()->getHomeUrl())
            <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                <x-filament-panels::logo />
            </a>
        @else
            <x-filament-panels::logo />
        @endif
    </div>
@endif
