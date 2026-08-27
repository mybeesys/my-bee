@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $documentTitle = __('fields.subscription_invoice');
    $client = $subscription->client;
    $brandLogo = system_brand_logo_url();
    $iconLogo = system_logo_icon_url();
    $isFree = $subscription->isFree();
    $billingLabel = match ($subscription->billing_period) {
        'yearly' => __('fields.yearly'),
        default => __('fields.monthly'),
    };
    $invoiceDate = $subscription->start_date ?? $subscription->created_at;
    $subtotal = (float) ($subscription->price_ex_tax ?? $subscription->price ?? 0);
    $discount = (float) ($subscription->discount_amount ?? 0);
    $tax = (float) ($subscription->tax_amount ?? 0);
    $total = (float) ($subscription->price ?? 0);
    $taxPercent = (float) ($subscription->tax_percent ?? 0);
    $lineDescription = trim(($subscription->plan?->name ?? '—') . ' · ' . $billingLabel);
    $companyDetails = array_values(array_filter([
        ['label' => __('fields.trn'), 'value' => $companyTrn, 'mono' => true],
        ['label' => __('fields.address'), 'value' => $companyAddress],
        ['label' => __('fields.phone'), 'value' => $companyPhone],
        ['label' => __('fields.mobile'), 'value' => $companyMobile ?? ''],
        ['label' => __('fields.email'), 'value' => $companyEmail],
    ], fn (array $item): bool => filled($item['value'])));
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} · {{ $subscription->invoice_no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --ink-muted: #64748b;
            --ink-soft: #94a3b8;
            --surface: #ffffff;
            --surface-muted: #f8fafc;
            --line: #e2e8f0;
            --brand-50: #fefce8;
            --brand-100: #fef9c3;
            --brand-400: #facc15;
            --brand-500: #eab308;
            --brand-600: #ca8a04;
            --brand-700: #a16207;
            --success: #059669;
            --success-soft: #ecfdf5;
            --shadow: 0 24px 64px rgb(15 23 42 / 0.08);
            --radius: 16px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: {{ $isRtl ? '"Cairo"' : '"Inter", "Cairo"' }}, system-ui, sans-serif;
            background: linear-gradient(180deg, #fffdf5 0%, #fffbeb 42%, #f8fafc 100%);
            color: var(--ink);
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(12px);
            background: rgb(255 255 255 / 0.86);
            border-bottom: 1px solid rgb(234 179 8 / 0.12);
        }

        .toolbar__inner {
            max-width: 880px;
            margin: 0 auto;
            padding: .9rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .toolbar__brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
        }

        .toolbar__logo {
            height: auto;
            width: 12rem;
            max-width: min(52vw, 12rem);
            max-height: 4rem;
            object-fit: contain;
            flex-shrink: 0;
        }

        .toolbar__title {
            margin: 0;
            font-size: .92rem;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .toolbar__subtitle {
            margin: .1rem 0 0;
            font-size: .78rem;
            color: var(--ink-muted);
        }

        .toolbar__actions {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-shrink: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            border-radius: 10px;
            font: inherit;
            font-size: .84rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }

        .btn--ghost {
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--ink);
            padding: .55rem .85rem;
        }

        .btn--ghost:hover {
            background: var(--surface-muted);
            transform: translateY(-1px);
        }

        .btn--primary {
            border: 0;
            background: var(--brand-600);
            color: #fff;
            padding: .55rem .95rem;
            box-shadow: 0 8px 20px rgb(234 179 8 / 0.28);
        }

        .btn--primary:hover {
            background: var(--brand-700);
            transform: translateY(-1px);
        }

        .page {
            max-width: 880px;
            margin: 0 auto;
            padding: 1.75rem 1rem 3rem;
        }

        .invoice {
            background: var(--surface);
            border: 1px solid rgb(226 232 240 / 0.95);
            border-radius: calc(var(--radius) + 4px);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .invoice__hero {
            position: relative;
            padding: 0;
            background: linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
            border-bottom: 1px solid var(--line);
            overflow: hidden;
        }

        .invoice__hero::before {
            content: '';
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--brand-400) 0%, var(--brand-600) 45%, #fbbf24 100%);
        }

        .invoice__hero::after {
            content: '';
            position: absolute;
            inset: -30% -10% auto auto;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgb(250 204 21 / 0.14) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) auto minmax(0, 1fr);
            gap: 0;
            align-items: stretch;
            padding: 1.75rem 2rem 1.65rem;
        }

        .hero-company {
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            min-width: 0;
            text-align: start;
        }

        .hero-brand {
            display: flex;
            align-items: center;
            gap: .85rem;
        }

        .hero-brand__logo {
            width: auto;
            height: 3.25rem;
            max-width: 9.5rem;
            object-fit: contain;
            flex-shrink: 0;
        }

        .hero-brand__text {
            min-width: 0;
        }

        .hero-brand__name {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            line-height: 1.25;
            color: var(--ink);
        }

        .hero-brand__tagline {
            margin: .2rem 0 0;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--brand-700);
        }

        .hero-company-list {
            display: grid;
            gap: .45rem;
            margin: 0;
            padding: 0;
        }

        .hero-company-list__row {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: .65rem .85rem;
            align-items: baseline;
            margin: 0;
        }

        .hero-company-list__label {
            margin: 0;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--ink-soft);
            white-space: nowrap;
        }

        .hero-company-list__value {
            margin: 0;
            font-size: .86rem;
            color: var(--ink-muted);
            line-height: 1.45;
            word-break: break-word;
        }

        .hero-company-list__value--mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: .82rem;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: .02em;
        }

        .hero-divider {
            width: 1px;
            margin: .35rem 1.75rem;
            background: linear-gradient(180deg, transparent 0%, var(--line) 12%, var(--line) 88%, transparent 100%);
        }

        .hero-document {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            min-width: 0;
            text-align: end;
        }

        .hero-document__head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
            justify-content: flex-end;
        }

        .hero-document__body {
            display: flex;
            align-items: stretch;
            justify-content: flex-end;
            gap: .85rem;
        }

        .hero-invoice-id {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: .35rem;
            min-width: 9.5rem;
            padding: .85rem 1rem;
            border: 1px solid rgb(226 232 240 / 0.95);
            border-radius: 14px;
            background: rgb(255 255 255 / 0.82);
            box-shadow: 0 10px 30px rgb(15 23 42 / 0.04);
        }

        .hero-invoice-id__label {
            margin: 0;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .hero-invoice-id__number {
            margin: 0;
            font-size: 1.28rem;
            font-weight: 700;
            letter-spacing: .03em;
            color: var(--ink);
            line-height: 1.2;
        }

        .hero-invoice-id__date {
            margin: .15rem 0 0;
            font-size: .78rem;
            color: var(--ink-muted);
        }

        .invoice-qr {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 8.5rem;
            height: 8.5rem;
            padding: .45rem;
            border: 1px solid rgb(226 232 240 / 0.95);
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 10px 30px rgb(15 23 42 / 0.05);
        }

        .invoice-qr img {
            width: 7.5rem;
            height: 7.5rem;
            display: block;
            border-radius: 8px;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }

        .invoice__badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .45rem;
            justify-content: flex-end;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .38rem .72rem;
            border-radius: 999px;
            font-size: .71rem;
            font-weight: 700;
            letter-spacing: .03em;
        }

        .badge--paid {
            background: var(--success-soft);
            color: var(--success);
            border: 1px solid #a7f3d0;
        }

        .badge--free {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge--doc {
            background: linear-gradient(180deg, #fffef5 0%, var(--brand-50) 100%);
            color: var(--brand-700);
            border: 1px solid rgb(250 204 21 / 0.45);
        }

        .invoice__body {
            padding: 1.75rem 2.25rem 2rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .panel {
            background: var(--surface-muted);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1.1rem 1.15rem;
        }

        .panel__label {
            margin: 0 0 .65rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .panel__name {
            margin: 0 0 .35rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--ink);
        }

        .panel__line {
            margin: .15rem 0;
            font-size: .88rem;
            color: var(--ink-muted);
        }

        .panel__amount {
            margin: .15rem 0 0;
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            color: var(--ink);
        }

        .panel__amount--free {
            color: var(--success);
            font-size: 1.35rem;
        }

        .lines {
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }

        .lines__head,
        .lines__row,
        .lines__summary-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
            padding: .95rem 1.15rem;
        }

        .lines__head {
            background: #f1f5f9;
            border-bottom: 1px solid var(--line);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--ink-soft);
        }

        .lines__row {
            border-bottom: 1px solid var(--line);
            background: var(--surface);
        }

        .line-title {
            font-size: .95rem;
            font-weight: 600;
            color: var(--ink);
        }

        .line-sub {
            margin-top: .2rem;
            font-size: .82rem;
            color: var(--ink-muted);
        }

        .line-amount {
            font-size: .95rem;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            text-align: {{ $isRtl ? 'left' : 'right' }};
        }

        .lines__summary {
            background: linear-gradient(180deg, #fffdf5 0%, #fffbeb 100%);
        }

        .lines__summary-row {
            padding-top: .7rem;
            padding-bottom: .7rem;
            font-size: .88rem;
            color: var(--ink-muted);
        }

        .lines__summary-row.total {
            border-top: 1px solid var(--line);
            padding-top: 1rem;
            padding-bottom: 1rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--ink);
        }

        .lines__summary-row.total .line-amount {
            font-size: 1.35rem;
            color: var(--brand-700);
        }

        .notice {
            margin-top: 1.25rem;
            padding: .95rem 1rem;
            border-radius: 12px;
            background: var(--success-soft);
            border: 1px solid #a7f3d0;
            color: #047857;
            font-size: .88rem;
        }

        .invoice__footer {
            padding: 1.15rem 2.25rem 1.5rem;
            border-top: 1px solid var(--line);
            background: #fffdf5;
            font-size: .8rem;
            color: var(--ink-muted);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: .75rem;
        }

        @media (max-width: 720px) {
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 1.25rem;
                padding: 1.35rem 1.15rem 1.25rem;
            }

            .hero-divider {
                display: none;
            }

            .hero-document__head,
            .hero-document__body {
                justify-content: flex-start;
            }

            .hero-document__body {
                flex-wrap: wrap;
            }

            .invoice__body,
            .invoice__footer {
                padding-left: 1.15rem;
                padding-right: 1.15rem;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .toolbar__actions .btn span {
                display: none;
            }
        }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { padding: 0; max-width: none; }
            .invoice { box-shadow: none; border: 0; border-radius: 0; }
        }
    </style>
</head>
<body>
<header class="toolbar">
    <div class="toolbar__inner">
        <div class="toolbar__brand">
            @if($brandLogo ?? $iconLogo)
                <img src="{{ $brandLogo ?? $iconLogo }}" alt="{{ $companyName }}" class="toolbar__logo">
            @endif
            <div>
                <p class="toolbar__title">{{ $documentTitle }}</p>
                <p class="toolbar__subtitle">{{ $companyName }}</p>
            </div>
        </div>
        <div class="toolbar__actions">
            <button type="button" class="btn btn--ghost" onclick="window.print()">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18M4.5 7.5h15M4.5 7.5v3.75A2.25 2.25 0 0 0 6.75 13.5h10.5a2.25 2.25 0 0 0 2.25-2.25V7.5m-15 0A2.25 2.25 0 0 1 6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5" />
                </svg>
                <span>{{ __('fields.preview_invoice') }}</span>
            </button>
        </div>
    </div>
</header>

<main class="page">
    <article class="invoice">
        <section class="invoice__hero">
            <div class="hero-grid">
                <div class="hero-company">
                    <div class="hero-brand">
                        @if($brandLogo ?? $iconLogo)
                            <img src="{{ $brandLogo ?? $iconLogo }}" alt="{{ $companyName }}" class="hero-brand__logo">
                        @endif
                        <div class="hero-brand__text">
                            <h1 class="hero-brand__name">{{ $companyName }}</h1>
                            <p class="hero-brand__tagline">{{ __('fields.company') }}</p>
                        </div>
                    </div>

                    @if(count($companyDetails) > 0)
                        <dl class="hero-company-list">
                            @foreach($companyDetails as $detail)
                                <div class="hero-company-list__row">
                                    <dt class="hero-company-list__label">{{ $detail['label'] }}</dt>
                                    <dd @class([
                                        'hero-company-list__value',
                                        'hero-company-list__value--mono' => $detail['mono'] ?? false,
                                    ])>{{ $detail['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>

                <div class="hero-divider" aria-hidden="true"></div>

                <div class="hero-document">
                    <div class="hero-document__head">
                        <span class="badge badge--doc">{{ $documentTitle }}</span>
                        @if($isFree)
                            <span class="badge badge--free">{{ __('fields.free') }}</span>
                        @else
                            <span class="badge badge--paid">{{ __('fields.settlement_status_paid') }}</span>
                        @endif
                    </div>

                    <div class="hero-document__body">
                        <div class="hero-invoice-id">
                            <p class="hero-invoice-id__label">{{ __('fields.revenue_invoice_number') }}</p>
                            <p class="hero-invoice-id__number">{{ $subscription->invoice_no }}</p>
                            <p class="hero-invoice-id__date">{{ __('fields.date') }}: {{ $invoiceDate?->format('d M Y') }}</p>
                        </div>

                        @if($qrPayload ?? null)
                            <div class="invoice-qr">
                                @if($qrDataUri ?? null)
                                    <img
                                        src="{{ $qrDataUri }}"
                                        alt="{{ ($qrKind ?? null) === \App\Services\InvoiceZatcaQrService::KIND_ZATCA ? __('fields.subscription_invoice_qr_hint') : __('fields.invoice_qr_document_hint') }}"
                                        id="subscription-invoice-qr-image"
                                        width="120"
                                        height="120"
                                        onerror="window.renderDocumentQrFallback && window.renderDocumentQrFallback('subscription-invoice-qr-image')"
                                    >
                                @else
                                    <img
                                        src=""
                                        alt="{{ __('fields.subscription_invoice_qr_hint') }}"
                                        id="subscription-invoice-qr-image"
                                        width="120"
                                        height="120"
                                    >
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="invoice__body">
            <div class="grid-2">
                <div class="panel">
                    <p class="panel__label">{{ __('fields.the_client') }}</p>
                    <p class="panel__name">{{ $client?->name ?? '—' }}</p>
                    @if($client?->email)<p class="panel__line">{{ $client->email }}</p>@endif
                    @if($client?->phone)<p class="panel__line">{{ $client->phone }}</p>@endif
                </div>

                <div class="panel">
                    <p class="panel__label">{{ __('fields.revenue_subscription_section') }}</p>
                    <p class="panel__line">{{ __('fields.date') }}: <strong>{{ $invoiceDate?->format('d M Y') }}</strong></p>
                    <p class="panel__line">{{ __('fields.subscription_plan') }}: <strong>{{ $subscription->plan?->name ?? '—' }}</strong></p>
                    <p class="panel__line">{{ __('fields.billing_period') }}: <strong>{{ $billingLabel }}</strong></p>
                    @if($isFree)
                        <p class="panel__amount panel__amount--free">{{ __('fields.free') }}</p>
                    @else
                        <p class="panel__amount">{{ $currency }} {{ format_amount($total) }}</p>
                    @endif
                </div>
            </div>

            @unless($isFree)
                <div class="lines">
                    <div class="lines__head">
                        <span>{{ __('fields.description') }}</span>
                        <span>{{ __('fields.amount') }}</span>
                    </div>

                    <div class="lines__row">
                        <div>
                            <div class="line-title">{{ $lineDescription }}</div>
                            <div class="line-sub">{{ __('fields.subscription_invoice') }}</div>
                        </div>
                        <div class="line-amount">{{ $currency }} {{ format_amount($subtotal + $discount) }}</div>
                    </div>

                    <div class="lines__summary">
                        @if($discount > 0)
                            <div class="lines__summary-row">
                                <span>{{ __('fields.revenue_discount') }}@if($subscription->coupon_code) ({{ $subscription->coupon_code }})@endif</span>
                                <span class="line-amount">− {{ $currency }} {{ format_amount($discount) }}</span>
                            </div>
                            <div class="lines__summary-row">
                                <span>{{ __('fields.revenue_before_tax') }}</span>
                                <span class="line-amount">{{ $currency }} {{ format_amount($subtotal) }}</span>
                            </div>
                        @endif

                        @if($tax > 0)
                            <div class="lines__summary-row">
                                <span>{{ __('fields.tax') }} ({{ format_amount($taxPercent) }}%)</span>
                                <span class="line-amount">{{ $currency }} {{ format_amount($tax) }}</span>
                            </div>
                        @endif

                        <div class="lines__summary-row total">
                            <span>{{ __('fields.revenue_total') }}</span>
                            <span class="line-amount">{{ $currency }} {{ format_amount($total) }}</span>
                        </div>
                    </div>
                </div>
            @else
                <div class="notice">{{ __('fields.revenue_free_plan_notice') }}</div>
            @endunless
        </section>

        <footer class="invoice__footer">
            <span>{{ $companyName }}@if(filled($companyTrn)) · {{ __('fields.trn') }}: {{ $companyTrn }}@endif</span>
            <span>{{ $documentTitle }} · {{ $subscription->invoice_no }}</span>
        </footer>
    </article>
</main>

@include('partials.document-qr-script', [
    'qrPayload' => $qrPayload ?? null,
    'qrImageId' => 'subscription-invoice-qr-image',
])
</body>
</html>
