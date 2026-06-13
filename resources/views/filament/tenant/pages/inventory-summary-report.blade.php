@php
    $report = $this->report;
    $rows = $report['rows'] ?? [];
    $isAr = app()->getLocale() === 'ar';
    $alignNum = $isAr ? 'text-left' : 'text-right';
@endphp

<x-filament-panels::page class="fi-page-inventory-summary">
    <div class="inventory-report space-y-6">
        <form wire:submit.prevent="loadReport">
            {{ $this->form }}
        </form>

        @if ($report)
            <div class="inventory-report__panel">
                <div class="inventory-report__header">
                    <h2 class="inventory-report__title">{{ __('fields.inventory_summary_report') }}</h2>
                    <p class="inventory-report__subtitle">
                        {{ filament()->getTenant()?->name }}
                        @if ($report['filters']['from'] || $report['filters']['to'])
                            — {{ __('fields.income_statement_period') }}:
                            {{ $report['filters']['from'] ? \Carbon\Carbon::parse($report['filters']['from'])->translatedFormat('d M Y') : '…' }}
                            {{ __('fields.income_statement_period_to') }}
                            {{ $report['filters']['to'] ? \Carbon\Carbon::parse($report['filters']['to'])->translatedFormat('d M Y') : '…' }}
                        @endif
                    </p>
                </div>

                <div class="inventory-report__scroll">
                    <table class="inventory-report__table">
                        <thead>
                            <tr>
                                <th class="{{ $isAr ? 'text-right' : 'text-left' }}">{{ __('fields.sku') }}</th>
                                <th class="{{ $isAr ? 'text-right' : 'text-left' }}">{{ __('fields.product') }}</th>
                                <th class="{{ $isAr ? 'text-right' : 'text-left' }}">{{ __('fields.warehouse') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_opening') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_purchased') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_sold') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_purchase_returns') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_transferred') }}</th>
                                <th class="{{ $alignNum }}">{{ __('fields.inventory_on_hand') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td class="font-mono text-xs">{{ $row['sku'] }}</td>
                                    <td>{{ $row['product_name'] }}</td>
                                    <td>{{ $row['warehouse_name'] }}</td>
                                    <td class="{{ $alignNum }} tabular-nums">{{ number_format($row['opening_inventory'], 0) }}</td>
                                    <td class="{{ $alignNum }} tabular-nums text-emerald-600">{{ number_format($row['purchased_quantity'], 0) }}</td>
                                    <td class="{{ $alignNum }} tabular-nums text-rose-600">{{ number_format($row['sales_quantity'], 0) }}</td>
                                    <td class="{{ $alignNum }} tabular-nums">{{ number_format($row['purchase_returns'], 0) }}</td>
                                    <td class="{{ $alignNum }} tabular-nums">{{ number_format($row['transferred_quantity'], 0) }}</td>
                                    <td class="{{ $alignNum }} tabular-nums font-semibold">{{ number_format($row['quantity_on_inventory'], 0) }}</td>
                                    <td class="text-center">
                                        <a
                                            href="{{ $this->detailUrl($row) }}"
                                            class="inventory-report__drill-link"
                                        >
                                            {{ __('fields.inventory_item_ledger') }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="inventory-report__empty">
                                        {{ __('fields.table_empty_state') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
