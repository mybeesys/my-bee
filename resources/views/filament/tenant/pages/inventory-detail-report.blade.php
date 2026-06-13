@php
    $report = $this->report;
    $stats = $report['stats'] ?? null;
    $unit = $stats['unit_name'] ?? '';
@endphp

<x-filament-panels::page class="fi-page-inventory-detail">
    <div class="inventory-report space-y-6">
        <div class="inventory-report__product-filter">
            {{ $this->productForm }}
        </div>

        @if ($report && $stats)
            <div class="inventory-report__stats-grid inventory-report__stats-grid--compact">
                <div class="inventory-report__stat-card">
                    <span class="inventory-report__stat-label">{{ __('fields.inventory_opening') }}</span>
                    <strong class="inventory-report__stat-value">{{ number_format($stats['opening_inventory'], 2) }}</strong>
                    @if ($unit)<span class="inventory-report__stat-unit">{{ $unit }}</span>@endif
                </div>
                <div class="inventory-report__stat-card inventory-report__stat-card--purchase">
                    <span class="inventory-report__stat-label">{{ __('fields.inventory_purchased') }}</span>
                    <strong class="inventory-report__stat-value">{{ number_format($stats['purchased_quantity'], 2) }}</strong>
                    @if ($unit)<span class="inventory-report__stat-unit">{{ $unit }}</span>@endif
                </div>
                <div class="inventory-report__stat-card inventory-report__stat-card--sales">
                    <span class="inventory-report__stat-label">{{ __('fields.inventory_sold') }}</span>
                    <strong class="inventory-report__stat-value">{{ number_format($stats['sales_quantity'], 2) }}</strong>
                    @if ($unit)<span class="inventory-report__stat-unit">{{ $unit }}</span>@endif
                </div>
                <div class="inventory-report__stat-card inventory-report__stat-card--return">
                    <span class="inventory-report__stat-label">{{ __('fields.inventory_purchase_returns') }}</span>
                    <strong class="inventory-report__stat-value">{{ number_format($stats['purchase_returns'], 2) }}</strong>
                    @if ($unit)<span class="inventory-report__stat-unit">{{ $unit }}</span>@endif
                </div>
                <div class="inventory-report__stat-card inventory-report__stat-card--on-hand">
                    <span class="inventory-report__stat-label">{{ __('fields.inventory_on_hand') }}</span>
                    <strong class="inventory-report__stat-value">{{ number_format($stats['quantity_on_inventory'], 2) }}</strong>
                    @if ($unit)<span class="inventory-report__stat-unit">{{ $unit }}</span>@endif
                </div>
            </div>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
