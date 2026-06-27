@php
    $report = $this->report;
    $stats = $report['stats'] ?? null;
    $currency = $stats['currency'] ?? main_currency_iso_code();

    $statCards = [
        ['key' => 'invoices_count', 'value' => number_format($stats['invoices_count'] ?? 0), 'hint' => 'sales_statement_invoices_count_hint', 'class' => ''],
        ['key' => 'sales_qty', 'value' => number_format($stats['sales_qty'] ?? 0, 2), 'hint' => 'sales_statement_sales_qty_hint', 'class' => 'sales-statement__stat-card--qty'],
        ['key' => 'returns_qty', 'value' => number_format($stats['returns_qty'] ?? 0, 2), 'hint' => 'sales_statement_returns_qty_hint', 'class' => 'sales-statement__stat-card--return'],
        ['key' => 'net_qty', 'value' => number_format($stats['net_qty'] ?? 0, 2), 'hint' => 'sales_statement_net_qty_hint', 'class' => ''],
        ['key' => 'gross_total', 'value' => format_amount($stats['gross_total'] ?? 0), 'hint' => 'sales_statement_gross_total_hint', 'class' => 'sales-statement__stat-card--gross', 'unit' => $currency],
        ['key' => 'discount_total', 'value' => format_amount($stats['discount_total'] ?? 0), 'hint' => 'sales_statement_discount_total_hint', 'class' => '', 'unit' => $currency],
        ['key' => 'tax_total', 'value' => format_amount($stats['tax_total'] ?? 0), 'hint' => 'sales_statement_tax_total_hint', 'class' => '', 'unit' => $currency],
        ['key' => 'returns_total', 'value' => format_amount($stats['returns_total'] ?? 0), 'hint' => 'sales_statement_returns_total_hint', 'class' => 'sales-statement__stat-card--return', 'unit' => $currency],
        ['key' => 'net_total', 'value' => format_amount($stats['net_total'] ?? 0), 'hint' => 'sales_statement_net_total_hint', 'class' => 'sales-statement__stat-card--net', 'unit' => $currency],
    ];
@endphp

<x-filament-panels::page class="fi-page-sales-statement">
    <div class="sales-statement space-y-6">
        <div class="sales-statement__intro">
            <p class="sales-statement__intro-text">{{ __('fields.sales_statement_intro') }}</p>
        </div>

        @if ($report && $stats)
            <div class="sales-statement__stats-grid">
                @foreach ($statCards as $card)
                    <div class="sales-statement__stat-card {{ $card['class'] }}">
                        <span class="sales-statement__stat-label">{{ __('fields.sales_statement_' . $card['key']) }}</span>
                        <span class="sales-statement__stat-hint">{{ __('fields.' . $card['hint']) }}</span>
                        <strong class="sales-statement__stat-value">{{ $card['value'] }}</strong>
                        @if (! empty($card['unit']))
                            <span class="sales-statement__stat-unit">{{ $card['unit'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
