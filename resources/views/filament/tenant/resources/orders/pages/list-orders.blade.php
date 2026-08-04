<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    @if (! plan_allows_store())
        @include('filament.tenant.components.store-upgrade-panel', ['context' => 'orders'])
    @else
        <div class="flex flex-col gap-y-6">
            <x-filament-panels::resources.tabs />

            @if (! empty($subscriptionLimitType))
                @include('filament.tenant.components.plan-limit-banner', [
                    'type' => $subscriptionLimitType,
                    'upgradeUrl' => \App\Filament\Tenant\Pages\Subscription::getUrl(),
                ])
            @endif

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    @endif
</x-filament-panels::page>
