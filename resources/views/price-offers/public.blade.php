@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $currency = $settings['main_currency'] ?? 'SAR';
    $companyName = $settings['company.name'] ?? $tenant->name;
    $companyAddress = $settings['company.address'] ?? ($tenant->store_address ?? '');
    $companyPhone = $settings['company.contact.phone'] ?? ($tenant->phone ?? '');
    $customerName = $priceOffer->customer?->name ?? '—';
    $total = $priceOffer->getItemsCost(true, true, true);
    $discountAmount = $priceOffer->details->sum('discount');
    $documentTitle = __('fields.price_offer');
    $terms = $tenant->store_terms_and_conditions ?? '';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} #{{ $priceOffer->no }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-50: #fefce8;
            --brand-100: #fef9c3;
            --brand-400: #facc15;
            --brand-500: #eab308;
            --brand-600: #ca8a04;
            --brand-700: #a16207;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Cairo", system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif;
            background: #f3f4f6;
            color: #111827;
            line-height: 1.5;
        }

        .invoice-navbar {
            position: relative;
            background: linear-gradient(180deg, #fffdf5 0%, #fffbeb 100%);
            border-bottom: 1px solid rgb(234 179 8 / 0.15);
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.04);
        }

        .invoice-navbar__inner {
            max-width: 900px;
            margin: 0 auto;
            padding: .85rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .invoice-navbar__brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            min-width: 0;
        }

        .invoice-navbar__logo {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 8px;
        }

        .invoice-navbar__text { min-width: 0; }

        .invoice-navbar__title {
            margin: 0;
            font-size: .95rem;
            font-weight: 700;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .invoice-navbar__subtitle {
            margin: .1rem 0 0;
            font-size: .78rem;
            color: #6b7280;
        }

        .invoice-navbar__action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 12px;
            border: 1px solid rgb(234 179 8 / 0.35);
            background: var(--brand-50);
            color: var(--brand-600);
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
            transition: background .2s, color .2s, border-color .2s, transform .15s;
        }

        .invoice-navbar__action:hover {
            background: var(--brand-100);
            border-color: var(--brand-400);
            color: var(--brand-700);
            transform: translateY(-1px);
        }

        .invoice-navbar__action svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .invoice-navbar__wave {
            display: block;
            width: 100%;
            height: 28px;
            line-height: 0;
            color: var(--brand-400);
        }

        .invoice-navbar__wave svg {
            display: block;
            width: 100%;
            height: 28px;
        }

        .page-wrap { padding: 1.5rem 1rem 2.5rem; }

        .page {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .content { padding: 2rem 1.5rem 2.5rem; }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: flex-start;
            margin-bottom: 2rem;
        }

        .brand img { max-height: 64px; max-width: 180px; object-fit: contain; }
        .brand h1 { margin: .75rem 0 .25rem; font-size: 1.35rem; }
        .brand p { margin: .15rem 0; color: #6b7280; font-size: .9rem; }

        .meta { text-align: {{ $isRtl ? 'left' : 'right' }}; }
        .meta h2 { margin: 0 0 .5rem; font-size: 1.5rem; }
        .meta p { margin: .2rem 0; color: #4b5563; }

        .meta-qr {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            align-items: {{ $isRtl ? 'flex-start' : 'flex-end' }};
            gap: .4rem;
        }

        .meta-qr__frame {
            width: 128px;
            height: 128px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 6px;
            background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .meta-qr__frame img,
        .meta-qr img {
            width: 112px;
            height: 112px;
            display: block;
            border-radius: 4px;
            background: #fff;
        }

        .meta-qr__label,
        .meta-qr span {
            font-size: .72rem;
            color: #6b7280;
            max-width: 140px;
            line-height: 1.35;
        }

        .party {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 1rem;
        }

        .card h3 {
            margin: 0 0 .5rem;
            font-size: .85rem;
            color: #6b7280;
        }

        .card p { margin: .15rem 0; }

        .alert {
            margin-bottom: 1.5rem;
            padding: 1rem 1.1rem;
            border-radius: 10px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .status-badge {
            display: inline-block;
            padding: .15rem .55rem;
            border-radius: 999px;
            font-size: .8rem;
            font-weight: 600;
        }

        .status-badge--active {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge--expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .section-title {
            margin: 2rem 0 .75rem;
            font-size: 1rem;
            color: #374151;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: .75rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: {{ $isRtl ? 'right' : 'left' }};
        }

        th { background: #f9fafb; font-size: .85rem; color: #374151; }
        td.num, th.num { text-align: {{ $isRtl ? 'left' : 'right' }}; white-space: nowrap; }

        .item-extras {
            margin-top: .25rem;
            font-size: .82rem;
            color: #6b7280;
        }

        .totals {
            margin-top: 1.5rem;
            margin-{{ $isRtl ? 'right' : 'left' }}: auto;
            width: min(100%, 320px);
        }

        .totals div {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: .45rem 0;
            border-bottom: 1px dashed #e5e7eb;
        }

        .totals .grand {
            font-size: 1.15rem;
            font-weight: 700;
            border-bottom: none;
            padding-top: .75rem;
        }

        .notes, .terms {
            margin-top: 1.5rem;
            color: #4b5563;
        }

        @media print {
            body { background: #fff; }
            .invoice-navbar { display: none; }
            .page-wrap { padding: 0; }
            .page { margin: 0; box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
<header class="invoice-navbar">
    <div class="invoice-navbar__inner">
        <div class="invoice-navbar__brand">
            @if($tenant->logo)
                <img src="{{ $tenant->logo }}" alt="{{ $companyName }}" class="invoice-navbar__logo">
            @endif
            <div class="invoice-navbar__text">
                <p class="invoice-navbar__title">{{ $documentTitle }} #{{ $priceOffer->no }}</p>
                <p class="invoice-navbar__subtitle">{{ $companyName }}</p>
            </div>
        </div>

        <button
            type="button"
            class="invoice-navbar__action"
            onclick="window.print()"
            title="{{ __('fields.preview_invoice') }}"
            aria-label="{{ __('fields.preview_invoice') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18M4.5 7.5h15M4.5 7.5v3.75A2.25 2.25 0 0 0 6.75 13.5h10.5a2.25 2.25 0 0 0 2.25-2.25V7.5m-15 0A2.25 2.25 0 0 1 6.75 5.25h10.5A2.25 2.25 0 0 1 19.5 7.5" />
            </svg>
        </button>
    </div>

    <div class="invoice-navbar__wave" aria-hidden="true">
        <svg viewBox="0 0 1440 48" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path fill="currentColor" fill-opacity="0.22" d="M0,24 C240,48 480,0 720,24 C960,48 1200,0 1440,24 L1440,48 L0,48 Z"/>
            <path fill="currentColor" fill-opacity="0.38" d="M0,30 C360,8 720,44 1080,22 C1260,12 1380,18 1440,24 L1440,48 L0,48 Z"/>
        </svg>
    </div>
</header>

<div class="page-wrap">
    <div class="page">
        <div class="content">
            @if($priceOffer->isExpired())
                <div class="alert">{{ __('fields.price_offer_expired_client_message') }}</div>
            @endif

            <div class="header">
                <div class="brand">
                    @if($tenant->logo)
                        <img src="{{ $tenant->logo }}" alt="{{ $companyName }}">
                    @endif
                    <h1>{{ $companyName }}</h1>
                    @if($companyAddress)<p>{{ $companyAddress }}</p>@endif
                    @if($companyPhone)<p>{{ $companyPhone }}</p>@endif
                </div>
                <div class="meta">
                    <h2>{{ $documentTitle }}</h2>
                    <p><strong>#{{ $priceOffer->no }}</strong></p>
                    <p>{{ __('fields.date') }}: {{ $priceOffer->created_at?->format('d-m-Y') }}</p>
                    @if($priceOffer->expires_at)
                        <p>{{ __('fields.price_offer_expires_at') }}: {{ $priceOffer->expires_at->format('d-m-Y') }}</p>
                    @endif
                    <p>
                        {{ __('fields.price_offer_status') }}:
                        <span class="status-badge {{ $priceOffer->isExpired() ? 'status-badge--expired' : 'status-badge--active' }}">
                            {{ $priceOffer->isExpired() ? __('fields.price_offer_expired') : __('fields.price_offer_active') }}
                        </span>
                    </p>
                    @include('partials.document-qr', [
                        'qrPayload' => $qrPayload ?? null,
                        'qrDataUri' => $qrDataUri ?? null,
                        'qrKind' => $qrKind ?? null,
                        'qrImageId' => 'price-offer-qr-image',
                    ])
                </div>
            </div>

            <div class="party">
                <div class="card">
                    <h3>{{ __('fields.the_client') }}</h3>
                    <p><strong>{{ $customerName }}</strong></p>
                </div>
                <div class="card">
                    <h3>{{ __('fields.invoice_total_with_tax') }}</h3>
                    <p><strong>{{ $currency }} {{ format_amount($total) }}</strong></p>
                </div>
            </div>

            @if(filled($priceOffer->description))
                <div class="notes">
                    <strong>{{ __('fields.description') }}:</strong>
                    <div>{{ $priceOffer->description }}</div>
                </div>
            @endif

            @if($priceOffer->details->isNotEmpty())
                <h3 class="section-title">{{ __('fields.products') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('fields.product') }}</th>
                        <th class="num">{{ __('fields.qty') }}</th>
                        <th class="num">{{ __('fields.price') }}</th>
                        <th class="num">{{ __('fields.discount') }}</th>
                        <th class="num">{{ __('fields.total') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($priceOffer->details as $detail)
                        @php
                            $lineTotal = ($detail->unit_price * $detail->qty) + $detail->extras_total - $detail->discount;
                        @endphp
                        <tr>
                            <td>
                                {{ $detail->item?->name ?? '—' }}
                                @if($detail->offerDetailsExtras->isNotEmpty())
                                    <div class="item-extras">
                                        @foreach($detail->offerDetailsExtras as $extra)
                                            <div>+ {{ $extra->name ?? $extra->productExtra?->name ?? '—' }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="num">{{ format_amount($detail->qty, 0) }}</td>
                            <td class="num">{{ format_amount($detail->unit_price) }}</td>
                            <td class="num">{{ format_amount($detail->discount) }}</td>
                            <td class="num">{{ format_amount($lineTotal) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if($priceOffer->services->isNotEmpty())
                <h3 class="section-title">{{ __('fields.services') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('fields.service') }}</th>
                        <th class="num">{{ __('fields.price') }}</th>
                        <th class="num">{{ __('fields.tax') }}</th>
                        <th class="num">{{ __('fields.total') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($priceOffer->services as $service)
                        @php
                            $serviceTaxPercent = collect($service->tax_profile_data['taxes'] ?? [])->sum('percent');
                            $serviceTax = $service->price * ($serviceTaxPercent / 100);
                            $serviceTotal = $service->price + ($priceOffer->prices_includes_taxes ? 0 : $serviceTax);
                        @endphp
                        <tr>
                            <td>
                                {{ $service->type?->name ?? '—' }}
                                @if(filled($service->description))
                                    <div class="item-extras">{{ $service->description }}</div>
                                @endif
                            </td>
                            <td class="num">{{ format_amount($service->price) }}</td>
                            <td class="num">{{ format_amount($serviceTax) }}</td>
                            <td class="num">{{ format_amount($serviceTotal) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if($priceOffer->additionalCosts->isNotEmpty())
                <h3 class="section-title">{{ __('fields.additional_costs') }}</h3>
                <table>
                    <thead>
                    <tr>
                        <th>{{ __('fields.additional_costs') }}</th>
                        <th class="num">{{ __('fields.cost') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($priceOffer->additionalCosts as $additionalCost)
                        <tr>
                            <td>
                                {{ $additionalCost->type?->name ?? '—' }}
                                @if(filled($additionalCost->statement))
                                    <div class="item-extras">{{ $additionalCost->statement }}</div>
                                @endif
                            </td>
                            <td class="num">{{ format_amount($additionalCost->cost) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <div class="totals">
                @if($priceOffer->getAdditionalCosts(true) > 0)
                    <div>
                        <span>{{ __('fields.additional_costs') }}</span>
                        <span>{{ $currency }} {{ format_amount($priceOffer->getAdditionalCosts(true)) }}</span>
                    </div>
                @endif
                @if($discountAmount > 0)
                    <div>
                        <span>{{ __('fields.discount') }}</span>
                        <span>{{ $currency }} {{ format_amount($discountAmount) }}</span>
                    </div>
                @endif
                @if(! $priceOffer->prices_includes_taxes && $priceOffer->getTaxesAsAmount() > 0)
                    <div>
                        <span>{{ __('fields.tax') }}</span>
                        <span>{{ $currency }} {{ format_amount($priceOffer->getTaxesAsAmount()) }}</span>
                    </div>
                @endif
                <div class="grand">
                    <span>{{ __('fields.invoice_total_with_tax') }}</span>
                    <span>{{ $currency }} {{ format_amount($total) }}</span>
                </div>
            </div>

            @if($priceOffer->notes)
                <div class="notes">
                    <strong>{{ __('fields.notes') }}:</strong>
                    <div>{{ $priceOffer->notes }}</div>
                </div>
            @endif

            @if(filled($terms))
                <div class="terms">
                    <strong>{{ __('fields.store_terms_and_conditions') }}:</strong>
                    <div>{!! nl2br(e(strip_tags($terms))) !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@include('partials.document-qr-script', [
    'qrPayload' => $qrPayload ?? null,
    'qrImageId' => 'price-offer-qr-image',
])
</body>
</html>
