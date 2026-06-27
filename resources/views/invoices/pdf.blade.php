<!DOCTYPE html>
<html lang="{{ $isRtl ? 'ar' : 'en' }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documentTitle }} #{{ $invoice->no }}</title>
    <style>
        @page {
            margin: 24px 28px;
        }

        * {
            font-family: "cairo", "DejaVu Sans", sans-serif;
        }

        html, body {
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 0;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
            padding-bottom: 18px;
        }

        .logo {
            max-height: 64px;
            max-width: 160px;
            margin-bottom: 8px;
        }

        .doc-title {
            font-size: 22px;
            font-weight: bold;
            margin: 0 0 10px;
            color: #111827;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px;
        }

        .muted {
            color: #4b5563;
            line-height: 1.6;
        }

        .meta-line {
            margin: 3px 0;
            color: #374151;
        }

        .meta-label {
            color: #6b7280;
        }

        .party-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 18px;
        }

        .party-box .label {
            font-size: 10px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .party-box .value {
            font-size: 13px;
            font-weight: bold;
        }

        .items th,
        .items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 9px 8px;
        }

        .items th {
            background: #f3f4f6;
            font-size: 10px;
            font-weight: bold;
            color: #374151;
        }

        .items td.product {
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }

        .items th.product,
        .items td.product {
            width: 38%;
        }

        .num {
            text-align: center;
            white-space: nowrap;
            direction: ltr;
            unicode-bidi: embed;
        }

        .ltr {
            direction: ltr;
            unicode-bidi: embed;
            display: inline-block;
        }

        .summary {
            width: 300px;
            margin-top: 16px;
            margin-{{ $isRtl ? 'right' : 'left' }}: 0;
            margin-{{ $isRtl ? 'left' : 'right' }}: auto;
        }

        .summary-row {
            display: table;
            width: 100%;
            padding: 6px 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .summary-row.grand {
            border-bottom: none;
            padding-top: 10px;
            font-size: 13px;
            font-weight: bold;
        }

        .summary-label,
        .summary-value {
            display: table-cell;
            vertical-align: middle;
        }

        .summary-label {
            text-align: {{ $isRtl ? 'right' : 'left' }};
            color: #374151;
        }

        .summary-value {
            text-align: {{ $isRtl ? 'left' : 'right' }};
            width: 42%;
            color: #111827;
        }

        .footer-text {
            margin-top: 18px;
            color: #4b5563;
            line-height: 1.7;
        }

        .footer-text .label {
            font-weight: bold;
            color: #111827;
        }

        .qr-box {
            margin-top: 10px;
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }

        .qr-box img {
            width: 96px;
            height: 96px;
        }

        .trn-line {
            color: #4b5563;
            margin: 2px 0;
        }
    </style>
</head>
<body>
<table class="header">
    <tr>
        @if($isRtl)
            <td width="55%">
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="logo" class="logo">
                @endif
                <div class="company-name">{{ $companyName }}</div>
                @if($trn)<div class="trn-line">{{ $trnLabel }}: <span class="ltr">{{ $trn }}</span></div>@endif
                @if($companyAddress)<div class="muted">{{ $companyAddress }}</div>@endif
                @if($companyPhone)<div class="muted ltr">{{ $companyPhone }}</div>@endif
            </td>
            <td width="45%">
                <div class="doc-title">{{ $documentTitle }}</div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['invoiceNo'] }}:</span>
                    <strong class="ltr">{{ $invoice->no }}</strong>
                </div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['date'] }}:</span>
                    <strong class="ltr">{{ $invoice->date?->format('d-m-Y') }}</strong>
                </div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['paymentStatus'] }}:</span>
                    <strong>{{ $paymentStatus }}</strong>
                </div>
                @if($qrDataUri)
                    <div class="qr-box">
                        <img src="{{ $qrDataUri }}" alt="QR">
                    </div>
                @endif
            </td>
        @else
            <td width="55%">
                @if($logoPath)
                    <img src="{{ $logoPath }}" alt="logo" class="logo">
                @endif
                <div class="company-name">{{ $companyName }}</div>
                @if($trn)<div class="trn-line">{{ $trnLabel }}: <span class="ltr">{{ $trn }}</span></div>@endif
                @if($companyAddress)<div class="muted">{{ $companyAddress }}</div>@endif
                @if($companyPhone)<div class="muted">{{ $companyPhone }}</div>@endif
            </td>
            <td width="45%" style="text-align: right;">
                <div class="doc-title">{{ $documentTitle }}</div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['invoiceNo'] }}:</span>
                    <strong>{{ $invoice->no }}</strong>
                </div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['date'] }}:</span>
                    <strong>{{ $invoice->date?->format('d-m-Y') }}</strong>
                </div>
                <div class="meta-line">
                    <span class="meta-label">{{ $labels['paymentStatus'] }}:</span>
                    <strong>{{ $paymentStatus }}</strong>
                </div>
                @if($qrDataUri)
                    <div class="qr-box">
                        <img src="{{ $qrDataUri }}" alt="QR">
                    </div>
                @endif
            </td>
        @endif
    </tr>
</table>

<div class="party-box">
    <div class="label">{{ $partyLabel }}</div>
    <div class="value">{{ $partyName }}</div>
</div>

<table class="items">
    <thead>
    <tr>
        <th class="product">{{ $labels['product'] }}</th>
        <th class="num">{{ $labels['qty'] }}</th>
        <th class="num">{{ $labels['price'] }}</th>
        <th class="num">{{ $labels['discount'] }}</th>
        <th class="num">{{ $labels['total'] }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($items as $item)
        <tr>
            <td class="product">{{ $item['name'] }}</td>
            <td class="num">{{ $item['qty'] }}</td>
            <td class="num">{{ $item['price'] }}</td>
            <td class="num">{{ $item['discount'] }}</td>
            <td class="num">{{ $item['total'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="summary">
    <div class="summary-row">
        <span class="summary-label">{{ $labels['totalBeforeVat'] }}</span>
        <span class="summary-value ltr">{{ $subtotalBeforeVat }} {{ $currencySymbol }}</span>
    </div>
    <div class="summary-row">
        <span class="summary-label">{{ $labels['vat'] }}</span>
        <span class="summary-value ltr">{{ $vatAmount }} {{ $currencySymbol }}</span>
    </div>
    <div class="summary-row grand">
        <span class="summary-label">{{ $labels['invoiceTotal'] }}</span>
        <span class="summary-value ltr">{{ $total }} {{ $currencySymbol }}</span>
    </div>
</div>

@if($amountInWordsLine)
    <div class="footer-text">{{ $amountInWordsLine }}</div>
@endif

@if($notes)
    <div class="footer-text">
        <span class="label">{{ $labels['notes'] }}:</span>
        {{ $notes }}
    </div>
@endif
</body>
</html>
