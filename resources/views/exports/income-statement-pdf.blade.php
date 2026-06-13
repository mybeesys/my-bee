<!DOCTYPE html>
<html lang="{{ $isAr ? 'ar' : 'en' }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('fields.income_statement') }}</title>
    <style>
        html, body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 24px;
        }

        .header {
            margin-bottom: 18px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 12px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 6px;
        }

        .meta {
            color: #6b7280;
            line-height: 1.6;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .summary-grid td {
            width: 25%;
            border: 1px solid #e5e7eb;
            padding: 10px;
            vertical-align: top;
        }

        .summary-label {
            color: #6b7280;
            font-size: 10px;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
        }

        .summary-currency {
            color: #6b7280;
            font-size: 10px;
        }

        .sales { color: #047857; }
        .purchases { color: #b45309; }
        .expenses { color: #be123c; }
        .net-positive { color: #047857; }
        .net-negative { color: #be123c; }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
        }

        .table th {
            background: #f9fafb;
            font-weight: bold;
        }

        .section-row td {
            background: #f3f4f6;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.04em;
        }

        .subtotal-row td {
            font-weight: bold;
            background: #fafafa;
        }

        .footer-row td {
            font-weight: bold;
            font-size: 13px;
            background: #eef2ff;
            border-top: 2px solid #c7d2fe;
        }

        .amount {
            text-align: {{ $isAr ? 'left' : 'right' }};
            white-space: nowrap;
        }

        .muted {
            color: #9ca3af;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">{{ __('fields.income_statement') }}</h1>
        <div class="meta">
            <div>{{ $tenantName }}</div>
            <div>{{ __('fields.income_statement_operational_basis') }}</div>
            @if ($statement['from'] || $statement['to'])
                <div>
                    {{ __('fields.income_statement_period') }}
                    {{ $statement['from'] ? \Carbon\Carbon::parse($statement['from'])->translatedFormat('d M Y') : '…' }}
                    {{ __('fields.income_statement_period_to') }}
                    {{ $statement['to'] ? \Carbon\Carbon::parse($statement['to'])->translatedFormat('d M Y') : '…' }}
                </div>
            @endif
        </div>
    </div>

    @php
        $net = (float) ($statement['net_income'] ?? 0);
        $netPositive = $net >= 0;
    @endphp

    <table class="summary-grid">
        <tr>
            <td>
                <div class="summary-label">{{ __('fields.income_statement_sales_section') }}</div>
                <div class="summary-value sales">{{ format_amount($statement['sales_total']) }}</div>
                <div class="summary-currency">{{ $statement['currency'] }}</div>
            </td>
            <td>
                <div class="summary-label">{{ __('fields.income_statement_purchases_section') }}</div>
                <div class="summary-value purchases">{{ format_amount($statement['purchases_total']) }}</div>
                <div class="summary-currency">{{ $statement['currency'] }}</div>
            </td>
            <td>
                <div class="summary-label">{{ __('fields.income_statement_expense_section') }}</div>
                <div class="summary-value expenses">{{ format_amount($statement['expenses_total']) }}</div>
                <div class="summary-currency">{{ $statement['currency'] }}</div>
            </td>
            <td>
                <div class="summary-label">{{ __('fields.income_statement_net_income') }}</div>
                <div class="summary-value {{ $netPositive ? 'net-positive' : 'net-negative' }}">{{ format_amount($net) }}</div>
                <div class="summary-currency">{{ $statement['currency'] }}</div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>{{ __('fields.income_statement_item') }}</th>
                <th class="amount">{{ __('fields.amount') }} ({{ $statement['currency'] }})</th>
            </tr>
        </thead>
        <tbody>
            <tr class="section-row">
                <td colspan="2">{{ __('fields.income_statement_sales_section') }}</td>
            </tr>
            @forelse ($statement['sales_lines'] as $line)
                <tr>
                    <td>
                        @if ($line->code)
                            {{ $line->code }} — {{ $line->name }}
                        @else
                            {{ $line->name }}
                        @endif
                    </td>
                    <td class="amount sales">{{ format_amount($line->net) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">{{ __('fields.income_statement_no_lines') }}</td>
                </tr>
            @endforelse
            <tr class="subtotal-row">
                <td>{{ __('fields.income_statement_total_sales') }}</td>
                <td class="amount sales">{{ format_amount($statement['sales_total']) }}</td>
            </tr>

            <tr class="section-row">
                <td colspan="2">{{ __('fields.income_statement_purchases_section') }}</td>
            </tr>
            @forelse ($statement['purchases_lines'] as $line)
                <tr>
                    <td>
                        @if ($line->code)
                            {{ $line->code }} — {{ $line->name }}
                        @else
                            {{ $line->name }}
                        @endif
                    </td>
                    <td class="amount purchases">{{ format_amount($line->net) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">{{ __('fields.income_statement_no_lines') }}</td>
                </tr>
            @endforelse
            <tr class="subtotal-row">
                <td>{{ __('fields.income_statement_total_purchases') }}</td>
                <td class="amount purchases">{{ format_amount($statement['purchases_total']) }}</td>
            </tr>

            <tr class="section-row">
                <td colspan="2">{{ __('fields.income_statement_expense_section') }}</td>
            </tr>
            @forelse ($statement['expense_lines'] as $line)
                <tr>
                    <td>
                        @if ($line->code)
                            {{ $line->code }} — {{ $line->name }}
                        @else
                            {{ $line->name }}
                        @endif
                    </td>
                    <td class="amount expenses">{{ format_amount($line->net) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="muted">{{ __('fields.income_statement_no_lines') }}</td>
                </tr>
            @endforelse
            <tr class="subtotal-row">
                <td>{{ __('fields.income_statement_total_expenses') }}</td>
                <td class="amount expenses">{{ format_amount($statement['expenses_total']) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td>{{ __('fields.income_statement_net_income') }}</td>
                <td class="amount {{ $netPositive ? 'net-positive' : 'net-negative' }}">{{ format_amount($net) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
