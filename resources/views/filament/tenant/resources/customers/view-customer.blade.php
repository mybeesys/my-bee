@php
    /** @var \App\Models\Customer $record */
    $record = $this->record;
    $statement = $this->statement;
    $isAr = app()->getLocale() === 'ar';
    $alignAmount = $isAr ? 'text-left' : 'text-right';
    $balanceDue = $statement['current_balance'] ?? 0;
    $balancePositive = $balanceDue > 0;
@endphp

<x-filament-panels::page
    @class([
        'fi-page-customer-view',
        'fi-resource-view-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    @php
        $relationManagers = $this->getRelationManagers();
        $hasCombinedRelationManagerTabsWithContent = $this->hasCombinedRelationManagerTabsWithContent();
    @endphp

    @if (count($relationManagers))
        <x-filament-panels::resources.relation-managers
            :active-locale="isset($activeLocale) ? $activeLocale : null"
            :active-manager="$this->activeRelationManager ?? ($hasCombinedRelationManagerTabsWithContent ? null : array_key_first($relationManagers))"
            :content-tab-label="$this->getContentTabLabel()"
            :content-tab-icon="$this->getContentTabIcon()"
            :content-tab-position="$this->getContentTabPosition()"
            :managers="$relationManagers"
            :owner-record="$record"
            :page-class="static::class"
        >
            @if ($hasCombinedRelationManagerTabsWithContent)
                <x-slot name="content">
                    <div class="customer-view space-y-6">
                        @include('filament.tenant.resources.customers.partials.overview-content')
                    </div>
                </x-slot>
            @endif
        </x-filament-panels::resources.relation-managers>
    @else
        <div class="customer-view space-y-6">
            @include('filament.tenant.resources.customers.partials.overview-content')
        </div>
    @endif
</x-filament-panels::page>
