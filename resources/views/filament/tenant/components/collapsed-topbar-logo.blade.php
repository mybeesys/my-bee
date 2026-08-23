@if (filament()->isSidebarCollapsibleOnDesktop() && filament()->hasNavigation() && ! filament()->hasTopNavigation())
    @php
        $brandLogoUrl = system_brand_logo_url() ?? system_logo_icon_url();
        $homeUrl = filament()->getHomeUrl();
    @endphp

    <div
        class="fi-topbar-collapsed-brand shrink-0"
        x-cloak
        x-show="! $store.sidebar.isOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
    >
        @if ($brandLogoUrl)
            @if ($homeUrl)
                <a {{ \Filament\Support\generate_href_html($homeUrl) }} class="fi-logo flex min-w-0 items-center">
                    <img
                        src="{{ $brandLogoUrl }}"
                        alt="{{ config('app.name', 'MY BEE') }}"
                        class="fi-logo-brand"
                    />
                </a>
            @else
                <img
                    src="{{ $brandLogoUrl }}"
                    alt="{{ config('app.name', 'MY BEE') }}"
                    class="fi-logo-brand"
                />
            @endif
        @elseif ($homeUrl)
            <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                <x-filament-panels::logo />
            </a>
        @else
            <x-filament-panels::logo />
        @endif
    </div>
@endif
