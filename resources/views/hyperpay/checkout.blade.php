@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $periodLabel = $payment->billing_period === 'yearly' ? __('fields.yearly') : __('fields.monthly');
    $planName = $payment->plan?->name ?? '';
    $currency = strtoupper((string) ($quote['currency'] ?? $payment->currency ?? 'SAR'));
    $subtotal = (float) ($quote['subtotal_ex_tax'] ?? $payment->amount);
    $taxAmount = (float) ($quote['tax_amount'] ?? 0);
    $taxPercent = (float) ($quote['tax_percent'] ?? 0);
    $total = (float) ($quote['total_inc_tax'] ?? $payment->amount);
    $money = fn (float $value) => number_format($value, 2);
    $brandList = array_values(array_filter(preg_split('/\s+/', strtoupper((string) $brands)) ?: []));
    $hasMada = in_array('MADA', $brandList, true);
    $hasVisa = in_array('VISA', $brandList, true);
    $hasMaster = in_array('MASTER', $brandList, true);
    $hasApple = in_array('APPLEPAY', $brandList, true);
    $defaultBrand = $hasMada ? 'MADA' : ($hasVisa ? 'VISA' : ($hasMaster ? 'MASTER' : 'APPLEPAY'));
    $initialName = $cardholder !== '' ? $cardholder : __('fields.hyperpay_card_holder_placeholder');
    $extraJs = $config->extraWidgetJs();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('fields.hyperpay_checkout_title') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cairo:400,600,700,800|inter:400,600,700" rel="stylesheet">
    <style>
        :root {
            --bee: #EAB308;
            --bee-hot: #F59E0B;
            --ink: #0F172A;
            --slate: #1E293B;
            --muted: #94A3B8;
            --line: rgba(255,255,255,.08);
            --ok: #34D399;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            min-height: 100vh;
            font-family: Cairo, Inter, ui-sans-serif, system-ui, sans-serif;
            background:
                radial-gradient(900px 420px at 100% -10%, rgba(234,179,8,.18), transparent 55%),
                radial-gradient(700px 360px at -10% 110%, rgba(16,185,129,.12), transparent 50%),
                linear-gradient(180deg, #020617 0%, var(--ink) 42%, #020617 100%);
            color: #F8FAFC;
        }
        .hp-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            height: 60px;
            padding: 0 22px;
            background: rgba(15,23,42,.72);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(16px);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .hp-bar img { height: 34px; display: block; }
        .hp-bar__secure {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: var(--ok);
        }
        .hp-bar__secure i {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--ok); box-shadow: 0 0 10px var(--ok);
        }
        .hp-cancel {
            appearance: none;
            background: transparent;
            border: 1px solid rgba(248,250,252,.14);
            color: #E2E8F0;
            border-radius: 999px;
            padding: 8px 14px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }
        .hp-wrap { width: min(1120px, calc(100% - 32px)); margin: 20px auto 32px; }
        .hp-steps { display: flex; justify-content: center; gap: 8px; margin: 0 0 16px; }
        .hp-steps span {
            font-size: 11px; color: var(--muted);
            padding: 4px 10px; border-radius: 999px;
            border: 1px solid rgba(255,255,255,.08);
        }
        .hp-steps span.is-on { color: #0F172A; background: var(--bee); border-color: var(--bee); font-weight: 800; }
        .hp-shell {
            direction: ltr;
            display: grid;
            grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }
        .hp-summary, .hp-pay, .hp-cardface, .hp-tabs, .hp-note, .hp-badges {
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .hp-summary, .hp-pay {
            background: linear-gradient(180deg, rgba(30,41,59,.92), rgba(15,23,42,.88));
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .hp-summary { padding: 22px; position: sticky; top: 78px; }
        .hp-summary__kicker {
            font-size: 11px; font-weight: 800; letter-spacing: .14em;
            text-transform: uppercase; color: var(--bee); margin: 0 0 8px;
        }
        .hp-summary h1 { margin: 0; font-size: 22px; line-height: 1.35; }
        .hp-summary__period { margin: 6px 0 18px; color: var(--muted); font-size: 13px; }
        .hp-lines { display: grid; gap: 10px; }
        .hp-line { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; color: #CBD5E1; }
        .hp-line strong { color: #F8FAFC; font-weight: 700; }
        .hp-total {
            display: flex; justify-content: space-between; align-items: baseline;
            gap: 12px; margin-top: 16px; padding-top: 14px;
            border-top: 1px dashed rgba(234,179,8,.35);
        }
        .hp-total span { color: var(--muted); font-size: 13px; }
        .hp-total b { font-size: 26px; color: var(--bee); font-weight: 800; }
        .hp-badges { display: grid; gap: 8px; margin-top: 18px; }
        .hp-badge {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; color: #CBD5E1;
            background: rgba(2,6,23,.35);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 9px 11px;
        }
        .hp-badge svg { width: 16px; height: 16px; flex: none; color: var(--bee); }
        .hp-pay { padding: 20px 20px 16px; min-width: 0; }
        .hp-pay h2 { margin: 0 0 4px; font-size: 18px; }
        .hp-pay > .hp-lead { margin: 0 0 14px; color: var(--muted); font-size: 13px; }
        .hp-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .hp-tab {
            appearance: none; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            border-radius: 14px; padding: 10px 12px;
            border: 1px solid rgba(255,255,255,.1);
            background: rgba(2,6,23,.4);
            color: #E2E8F0; font: inherit; font-size: 12px; font-weight: 800;
        }
        .hp-tab.is-on {
            border-color: var(--bee);
            box-shadow: 0 0 0 3px rgba(234,179,8,.18);
            background: rgba(234,179,8,.12);
        }
        .hp-tab svg, .hp-tab img { height: 18px; width: auto; display: block; }
        .hp-cardface {
            position: relative;
            height: 188px;
            border-radius: 18px;
            padding: 18px 20px;
            margin-bottom: 16px;
            overflow: hidden;
            transform: perspective(900px) rotateX(6deg);
            transition: background .35s ease, box-shadow .35s ease, transform .35s ease;
            box-shadow: 0 18px 40px rgba(0,0,0,.28);
        }
        .hp-cardface::after {
            content: "";
            position: absolute; inset: 0;
            background: linear-gradient(115deg, rgba(255,255,255,.22), transparent 42%, rgba(255,255,255,.06));
            pointer-events: none;
        }
        .hp-cardface[data-brand="MADA"] {
            background: linear-gradient(135deg, #064E3B 0%, #0F766E 48%, #115E59 100%);
        }
        .hp-cardface[data-brand="VISA"] {
            background: linear-gradient(135deg, #1E3A8A 0%, #1D4ED8 52%, #0F172A 100%);
        }
        .hp-cardface[data-brand="MASTER"] {
            background: linear-gradient(135deg, #111827 0%, #1F2937 46%, #78350F 100%);
        }
        .hp-cardface[data-brand="APPLEPAY"] {
            background: linear-gradient(135deg, #09090B 0%, #27272A 100%);
        }
        .hp-cardface__top, .hp-cardface__bottom {
            position: relative; z-index: 1;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .hp-chip {
            width: 42px; height: 32px; border-radius: 6px;
            background: linear-gradient(180deg, #FDE68A, #D97706);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.35);
        }
        .hp-cardface__logo { font-size: 13px; font-weight: 800; letter-spacing: .12em; }
            .hp-pan {
            position: relative; z-index: 1;
            margin: 28px 0 18px;
            font-family: Inter, ui-monospace, monospace;
            font-size: 22px;
            letter-spacing: .22em;
            direction: ltr;
            unicode-bidi: isolate;
            text-align: left;
            font-variant-numeric: tabular-nums;
        }
        .hp-meta { font-size: 11px; color: rgba(255,255,255,.72); }
        .hp-meta b { display: block; margin-top: 4px; color: #fff; font-size: 13px; letter-spacing: .04em; }
        .hp-meta b[dir="ltr"] { direction: ltr; unicode-bidi: isolate; text-align: left; letter-spacing: .12em; font-variant-numeric: tabular-nums; }
        .hp-widget {
            min-height: 210px;
            direction: ltr;
            unicode-bidi: isolate;
            text-align: left;
        }
        .hp-widget .wpwl-label {
            direction: rtl;
            text-align: right;
            unicode-bidi: isolate;
        }
        .hp-note {
            margin: 14px 0 0;
            font-size: 12px; line-height: 1.55; color: #FDE68A;
            background: rgba(234,179,8,.08);
            border: 1px solid rgba(234,179,8,.22);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .wpwl-container { max-width: 100% !important; margin: 0 !important; direction: ltr !important; }
        .wpwl-form {
            max-width: 100% !important; margin: 0 !important; padding: 0 !important;
            background: transparent !important; border: 0 !important; box-shadow: none !important;
            direction: ltr !important;
        }
        .wpwl-group { margin-bottom: 12px !important; direction: ltr !important; }
        .wpwl-group-cardNumber, .wpwl-group-expiry, .wpwl-group-cvv, .wpwl-group-cardHolder {
            direction: ltr !important;
        }
        .wpwl-label { color: #E2E8F0 !important; font-size: 12px !important; font-weight: 700 !important; margin-bottom: 6px !important; }
        .wpwl-control, .wpwl-control-iframe, input.wpwl-control {
            min-height: 46px !important;
            border-radius: 12px !important;
            border: 1px solid rgba(255,255,255,.12) !important;
            background: rgba(2,6,23,.55) !important;
            color: #F8FAFC !important;
            direction: ltr !important;
            unicode-bidi: isolate !important;
            text-align: left !important;
        }
        .wpwl-control:focus, .wpwl-control-iframe:focus {
            outline: none !important;
            border-color: var(--bee) !important;
            box-shadow: 0 0 0 4px rgba(234,179,8,.22) !important;
        }
        .wpwl-button {
            width: 100% !important;
            min-height: 50px !important;
            margin-top: 6px !important;
            border: 0 !important;
            border-radius: 14px !important;
            background: linear-gradient(180deg, var(--bee), var(--bee-hot)) !important;
            color: #0F172A !important;
            font-weight: 800 !important;
            font-size: 15px !important;
            box-shadow: 0 10px 24px rgba(245,158,11,.28) !important;
            direction: rtl !important;
            letter-spacing: 0 !important;
        }
        .wpwl-form > .wpwl-group-brand,
        .wpwl-wrapper-brand {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            clip: rect(0 0 0 0) !important;
        }
        html.hp-busy .wpwl-button {
            pointer-events: none;
            opacity: .75;
        }
        html.hp-busy .wpwl-button::after {
            content: "";
            display: inline-block;
            width: 14px; height: 14px; margin-inline-start: 8px;
            border: 2px solid #0F172A; border-right-color: transparent; border-radius: 50%;
            vertical-align: -2px;
            animation: hp-spin .7s linear infinite;
        }
        @keyframes hp-spin { to { transform: rotate(360deg); } }
        .hp-modal {
            display: none; position: fixed; inset: 0; z-index: 50;
            background: rgba(2,6,23,.72); place-items: center; padding: 16px;
        }
        .hp-modal.is-open { display: grid; }
        .hp-modal__box {
            width: min(420px, 100%);
            background: var(--slate);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            direction: {{ $isRtl ? 'rtl' : 'ltr' }};
        }
        .hp-modal__box p { margin: 0 0 16px; color: #E2E8F0; line-height: 1.6; }
        .hp-modal__actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .hp-modal__actions a, .hp-modal__actions button {
            flex: 1; min-width: 140px; text-align: center; text-decoration: none;
            border-radius: 12px; padding: 11px 12px; font: inherit; font-weight: 800; cursor: pointer;
        }
        .hp-modal__stay { background: var(--bee); color: #0F172A; border: 0; }
        .hp-modal__leave { background: transparent; color: #F8FAFC; border: 1px solid rgba(255,255,255,.16); }
        @media (max-width: 860px) {
            .hp-shell { grid-template-columns: 1fr; }
            .hp-summary { position: static; }
            .hp-cardface { height: 170px; transform: none; }
            .hp-wrap { width: calc(100% - 20px); margin-top: 12px; }
        }
    </style>
    <script>
        var wpwlOptions = {
            paymentTarget: "_top",
            locale: @json($config->widgetLocale()),
            style: "plain",
            brandDetection: true,
            numberFormatting: true,
            labels: {
                cardHolder: @json(__('fields.hyperpay_label_card_holder')),
                cardNumber: @json(__('fields.hyperpay_label_card_number')),
                expiryDate: @json(__('fields.hyperpay_label_expiry')),
                cvv: @json(__('fields.hyperpay_label_cvv'))
            },
            iframeStyles: {
                "card-number-placeholder": {
                    "color": "#94A3B8",
                    "font-size": "16px",
                    "font-family": "Inter,monospace",
                    "direction": "ltr",
                    "text-align": "left"
                },
                "card-number-input": {
                    "color": "#F8FAFC",
                    "font-size": "16px",
                    "font-family": "Inter,monospace",
                    "direction": "ltr",
                    "text-align": "left",
                    "letter-spacing": "0.12em"
                },
                "cvv-placeholder": {
                    "color": "#94A3B8",
                    "font-size": "16px",
                    "font-family": "Inter,monospace",
                    "direction": "ltr",
                    "text-align": "left"
                },
                "cvv-input": {
                    "color": "#F8FAFC",
                    "font-size": "16px",
                    "direction": "ltr",
                    "text-align": "left"
                }
            },
            onReady: function () {
                var button = document.querySelector(".wpwl-button");
                if (button) {
                    button.textContent = @json(__('fields.hyperpay_checkout_pay'));
                }
                if (window.MyBeePay) {
                    window.MyBeePay.bindWidget();
                } else {
                    window.MyBeePayQueue = window.MyBeePayQueue || [];
                    window.MyBeePayQueue.push(["bindWidget"]);
                }
            },
            onChangeBrand: function (brand) {
                if (window.MyBeePay) {
                    window.MyBeePay.setBrand(brand);
                } else {
                    window.MyBeePayQueue = window.MyBeePayQueue || [];
                    window.MyBeePayQueue.push(["setBrand", brand]);
                }
            },
            onBeforeSubmitCard: function () {
                var holderEl = document.querySelector(".wpwl-control-cardHolder, input[name='card.holder']");
                var holder = holderEl && holderEl.value ? String(holderEl.value).trim() : "";
                if (!holder || /[^\u0000-\u007F]/.test(holder) || !/[A-Za-z]/.test(holder)) {
                    alert(@json(__('fields.hyperpay_card_holder_latin')));
                    document.documentElement.classList.remove("hp-busy");
                    return false;
                }
                document.documentElement.classList.add("hp-busy");
                return true;
            }
        };
    </script>
    <script
        src="{{ $widgetUrl }}"
        @if (filled($payment->integrity)) integrity="{{ $payment->integrity }}" crossorigin="anonymous" @endif
    ></script>
</head>
<body>
    <header class="hp-bar">
        <img src="{{ system_brand_logo_url() }}" alt="MyBee">
        <span class="hp-bar__secure"><i></i>{{ __('fields.hyperpay_checkout_secure') }}</span>
        <button type="button" class="hp-cancel" data-open-cancel>{{ __('fields.hyperpay_checkout_cancel') }}</button>
    </header>

    <main class="hp-wrap">
        @if (! empty($isRegistration))
            <div class="hp-steps">
                <span class="is-on">{{ __('fields.registration_step_choose_plan') }}</span>
                <span>{{ __('fields.registration_step_create_account') }}</span>
            </div>
        @endif

        <div class="hp-shell">
            <aside class="hp-summary">
                <p class="hp-summary__kicker">{{ __('fields.hyperpay_order_title') }}</p>
                <h1>{{ $planName }}</h1>
                <p class="hp-summary__period">{{ __('fields.hyperpay_plan_line', ['plan' => $planName, 'period' => $periodLabel]) }}</p>

                <div class="hp-lines">
                    <div class="hp-line">
                        <span>{{ __('fields.subscription_subtotal_ex_tax') }}</span>
                        <strong>{{ $currency }} {{ $money($subtotal) }}</strong>
                    </div>
                    @if (! empty($quote['discount_amount']) && (float) $quote['discount_amount'] > 0)
                        <div class="hp-line">
                            <span>{{ __('fields.discount') }}</span>
                            <strong>− {{ $currency }} {{ $money((float) $quote['discount_amount']) }}</strong>
                        </div>
                    @endif
                    @if ($taxAmount > 0)
                        <div class="hp-line">
                            <span>{{ __('fields.subscription_tax_amount', ['vat' => rtrim(rtrim(number_format($taxPercent, 2, '.', ''), '0'), '.')]) }}</span>
                            <strong>{{ $currency }} {{ $money($taxAmount) }}</strong>
                        </div>
                    @endif
                </div>

                <div class="hp-total">
                    <span>{{ __('fields.total') }}</span>
                    <b>{{ $currency }} {{ $money($total) }}</b>
                </div>

                <div class="hp-badges">
                    <div class="hp-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l8 4v6c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg>
                        {{ __('fields.hyperpay_checkout_ssl') }}
                    </div>
                    <div class="hp-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M7 7V5a5 5 0 0 1 10 0v2"/></svg>
                        {{ __('fields.hyperpay_checkout_pci') }}
                    </div>
                    <div class="hp-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg>
                        {{ __('fields.hyperpay_checkout_hp_secured') }}
                    </div>
                </div>
            </aside>

            <section class="hp-pay">
                <h2>{{ __('fields.hyperpay_checkout_title') }}</h2>
                <p class="hp-lead">{{ __('fields.hyperpay_checkout_subtitle', ['plan' => $planName]) }}</p>

                <div class="hp-tabs" role="tablist">
                    @if ($hasMada)
                        <button type="button" class="hp-tab {{ $defaultBrand === 'MADA' ? 'is-on' : '' }}" data-brand="MADA" aria-selected="{{ $defaultBrand === 'MADA' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 64 18" width="46" height="16" aria-hidden="true">
                                <rect width="64" height="18" rx="3" fill="#00A651"/>
                                <text x="32" y="13" text-anchor="middle" fill="#fff" font-size="10" font-family="Inter,Arial" font-weight="800">mada</text>
                            </svg>
                            {{ __('fields.hyperpay_checkout_tab_mada') }}
                        </button>
                    @endif
                    @if ($hasVisa)
                        <button type="button" class="hp-tab {{ $defaultBrand === 'VISA' ? 'is-on' : '' }}" data-brand="VISA" aria-selected="{{ $defaultBrand === 'VISA' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 40 18" width="28" height="16" aria-hidden="true">
                                <rect width="40" height="18" rx="3" fill="#1A1F71"/>
                                <text x="20" y="13" text-anchor="middle" fill="#fff" font-size="8" font-family="Inter,Arial" font-weight="800">VISA</text>
                            </svg>
                            {{ __('fields.hyperpay_checkout_tab_visa') }}
                        </button>
                    @endif
                    @if ($hasMaster)
                        <button type="button" class="hp-tab {{ $defaultBrand === 'MASTER' ? 'is-on' : '' }}" data-brand="MASTER" aria-selected="{{ $defaultBrand === 'MASTER' ? 'true' : 'false' }}">
                            <svg viewBox="0 0 28 18" width="22" height="16" aria-hidden="true">
                                <circle cx="11" cy="9" r="7" fill="#EB001B"/>
                                <circle cx="17" cy="9" r="7" fill="#F79E1B"/>
                            </svg>
                            {{ __('fields.hyperpay_checkout_tab_master') }}
                        </button>
                    @endif
                    @if ($hasApple)
                        <button type="button" class="hp-tab {{ $defaultBrand === 'APPLEPAY' ? 'is-on' : '' }}" data-brand="APPLEPAY" aria-selected="{{ $defaultBrand === 'APPLEPAY' ? 'true' : 'false' }}">
                            {{ __('fields.hyperpay_checkout_tab_apple') }}
                        </button>
                    @endif
                </div>

                <div class="hp-cardface" data-brand="{{ $defaultBrand }}" id="hp-cardface">
                    <div class="hp-cardface__top">
                        <span class="hp-chip" aria-hidden="true"></span>
                        <span class="hp-cardface__logo" id="hp-card-logo">{{ $defaultBrand === 'MADA' ? 'mada' : ($defaultBrand === 'MASTER' ? 'mastercard' : $defaultBrand) }}</span>
                    </div>
                    <div class="hp-pan" id="hp-card-pan" dir="ltr">•••• •••• •••• ••••</div>
                    <div class="hp-cardface__bottom">
                        <div class="hp-meta">{{ __('fields.hyperpay_label_card_holder') }}<b id="hp-card-name">{{ $initialName }}</b></div>
                        <div class="hp-meta">{{ __('fields.hyperpay_label_expiry') }}<b id="hp-card-exp" dir="ltr">MM/YY</b></div>
                    </div>
                </div>

                <div class="hp-widget">
                    <form action="{{ $resultUrl }}" class="paymentWidgets" data-brands="{{ $brands }}"></form>
                </div>

                <p class="hp-note">{{ __('fields.hyperpay_checkout_note') }}</p>
            </section>
        </div>
    </main>

    <div class="hp-modal" id="hp-cancel-modal" role="dialog" aria-modal="true">
        <div class="hp-modal__box">
            <p>{{ __('fields.hyperpay_checkout_cancel_confirm') }}</p>
            <div class="hp-modal__actions">
                <button type="button" class="hp-modal__stay" data-close-cancel>{{ __('fields.hyperpay_checkout_stay') }}</button>
                <a class="hp-modal__leave" href="{{ $cancelUrl }}">{{ __('fields.hyperpay_checkout_cancel') }}</a>
            </div>
        </div>
    </div>

    @if ($extraJs !== '' && ! str_contains($extraJs, 'wpwlOptions'))
        {!! $extraJs !!}
    @endif

    <script>
        window.MyBeePay = (function () {
            var face = document.getElementById("hp-cardface");
            var panEl = document.getElementById("hp-card-pan");
            var nameEl = document.getElementById("hp-card-name");
            var expEl = document.getElementById("hp-card-exp");
            var logoEl = document.getElementById("hp-card-logo");
            var placeholderName = @json($initialName);
            var placeholderHolder = @json(__('fields.hyperpay_card_holder_placeholder'));
            var logos = { MADA: "mada", VISA: "VISA", MASTER: "mastercard", APPLEPAY: "Apple Pay" };
            var bound = false;
            var state = { pan: "", name: "", exp: "", expMonth: "", expYear: "" };

            function brandFromValue(value) {
                if (!value) return null;
                if (typeof value === "object") {
                    value = value.brand || value.name || value.code || "";
                }
                value = String(value).toUpperCase();
                if (value.indexOf("MADA") !== -1) return "MADA";
                if (value.indexOf("VISA") !== -1) return "VISA";
                if (value.indexOf("MASTER") !== -1) return "MASTER";
                if (value.indexOf("APPLE") !== -1) return "APPLEPAY";
                return null;
            }

            function detectBrandFromBin(digits) {
                if (!digits || digits.length < 2) return null;
                if (/^(5[1-5]|2[2-7])/.test(digits)) return "MASTER";
                if (digits.charAt(0) === "4") return null;
                return null;
            }

            function setBrand(brand) {
                brand = brandFromValue(brand);
                if (!brand) return;
                face.setAttribute("data-brand", brand);
                logoEl.textContent = logos[brand] || brand;
                document.querySelectorAll(".hp-tab").forEach(function (tab) {
                    var on = tab.getAttribute("data-brand") === brand;
                    tab.classList.toggle("is-on", on);
                    tab.setAttribute("aria-selected", on ? "true" : "false");
                });
            }

            function maskPan(raw) {
                var digits = String(raw || "").replace(/\D/g, "").slice(0, 19);
                var first = "••••";
                var last = "••••";
                if (digits.length) {
                    first = (digits.slice(0, 4) + "••••").slice(0, 4);
                }
                if (digits.length >= 13) {
                    last = digits.length > 16 ? digits.slice(-4) : (digits.slice(12, 16) + "••••").slice(0, 4);
                }
                return first + " •••• •••• " + last;
            }

            function formatExpiry() {
                var month = String(state.expMonth || "").replace(/\D/g, "");
                var year = String(state.expYear || "").replace(/\D/g, "");
                var combined = String(state.exp || "");

                if (combined) {
                    var parts = combined.split(/[\s\/\-]+/).filter(Boolean);
                    if (parts.length >= 2) {
                        if (!month) month = String(parts[0]).replace(/\D/g, "");
                        if (!year) year = String(parts[1]).replace(/\D/g, "");
                    } else {
                        var digits = combined.replace(/\D/g, "");
                        if (digits.length === 4 && parseInt(digits, 10) >= 2000) {
                            if (!year) year = digits;
                        } else if (digits.length >= 3 && parseInt(digits.slice(0, 2), 10) >= 1 && parseInt(digits.slice(0, 2), 10) <= 12) {
                            if (!month) month = digits.slice(0, 2);
                            if (!year) year = digits.slice(2);
                        } else if (digits.length && !month) {
                            month = digits.slice(0, 2);
                        }
                    }
                }

                if (year.length >= 4) year = year.slice(-2);
                year = year.slice(0, 2);

                var monthNum = parseInt(month.slice(0, 2), 10);
                var mmOut = "MM";
                if (monthNum >= 1 && monthNum <= 12) {
                    mmOut = (monthNum < 10 ? "0" : "") + monthNum;
                } else if (month.length === 1 && monthNum === 0) {
                    mmOut = "0" + month;
                } else if (month.length && monthNum >= 1 && monthNum <= 12) {
                    mmOut = (monthNum < 10 ? "0" : "") + monthNum;
                }

                var yyOut = year ? (year + "YY").slice(0, 2) : "YY";
                if (mmOut === "MM" && yyOut === "YY") return "MM/YY";
                return mmOut + "/" + yyOut;
            }

            function paint() {
                panEl.textContent = maskPan(state.pan);
                nameEl.textContent = state.name || placeholderName || placeholderHolder;
                expEl.textContent = formatExpiry();
                var detected = detectBrandFromBin(String(state.pan).replace(/\D/g, ""));
                if (detected) setBrand(detected);
            }

            function classify(el) {
                if (!el) return "";
                var hay = (
                    (el.className && el.className.toString ? el.className.toString() : "") +
                    " " + (el.name || "") + " " + (el.id || "") +
                    " " + (el.getAttribute("data-action") || "")
                ).toLowerCase();
                if (hay.indexOf("cardnumber") !== -1 || hay.indexOf("card.number") !== -1 || hay.indexOf("card-number") !== -1) {
                    return "pan";
                }
                if (hay.indexOf("cardholder") !== -1 || hay.indexOf("card.holder") !== -1) {
                    return "name";
                }
                if (hay.indexOf("expirymonth") !== -1 || hay.indexOf("expiry-month") !== -1 || hay.indexOf("card.expirymonth") !== -1) {
                    return "expMonth";
                }
                if (hay.indexOf("expiryyear") !== -1 || hay.indexOf("expiry-year") !== -1 || hay.indexOf("card.expiryyear") !== -1) {
                    return "expYear";
                }
                if (hay.indexOf("expiry") !== -1 || hay.indexOf("expir") !== -1) {
                    return "exp";
                }
                return "";
            }

            function readNode(el) {
                if (!el) return "";
                if (el.tagName === "IFRAME") {
                    try {
                        var doc = el.contentDocument || (el.contentWindow && el.contentWindow.document);
                        var inner = doc && doc.querySelector("input, [contenteditable='true']");
                        if (inner) return String(inner.value || inner.textContent || "");
                    } catch (err) {}
                    return "";
                }
                return String(el.value || "");
            }

            function assignExpiry(kind, value) {
                var digits = String(value || "").replace(/\D/g, "");
                if (kind === "expMonth") {
                    state.expMonth = value;
                    return;
                }
                if (kind === "expYear") {
                    state.expYear = value;
                    return;
                }
                if (kind !== "exp" || !value) return;
                if (/^20\d{2}$/.test(digits)) {
                    state.expYear = digits;
                    return;
                }
                state.exp = value;
                if (value.indexOf("/") !== -1 || value.indexOf("-") !== -1) {
                    var parts = String(value).split(/[\s\/\-]+/).filter(Boolean);
                    if (parts[0]) state.expMonth = parts[0];
                    if (parts[1]) state.expYear = parts[1];
                }
            }

            function applyField(el) {
                var kind = classify(el);
                var value = readNode(el);
                if (!kind || value === "") {
                    var group = el.closest && el.closest(".wpwl-group");
                    if (group) {
                        if (group.className.indexOf("cardNumber") !== -1) kind = "pan";
                        else if (group.className.indexOf("cardHolder") !== -1) kind = "name";
                        else if (group.className.indexOf("expiry") !== -1) kind = "exp";
                    }
                }
                if (kind === "pan") state.pan = value || state.pan;
                if (kind === "name" && value) state.name = value;
                assignExpiry(kind, value);
                if (kind) paint();
            }

            function fromEvent(e) {
                var el = e.target;
                if (!el) return;
                if (el.tagName === "INPUT" || el.tagName === "SELECT" || el.isContentEditable) {
                    applyField(el);
                }
            }

            function scanWidget() {
                var root = document.querySelector(".wpwl-form") || document.querySelector(".hp-widget");
                if (!root) return;
                root.querySelectorAll("input, select, iframe").forEach(function (el) {
                    var value = readNode(el);
                    if (!value) return;
                    var kind = classify(el);
                    if (!kind && el.closest) {
                        var group = el.closest(".wpwl-group");
                        if (group && group.className.indexOf("cardNumber") !== -1) kind = "pan";
                        if (group && group.className.indexOf("cardHolder") !== -1) kind = "name";
                        if (group && group.className.indexOf("expiry") !== -1) kind = "exp";
                    }
                    if (kind === "pan") state.pan = value;
                    if (kind === "name") state.name = value;
                    assignExpiry(kind, value);
                });
                paint();
            }

            function clickWidgetBrand(brand) {
                var variants = [
                    ".wpwl-brand-" + brand,
                    ".wpwl-brand-" + brand.toLowerCase(),
                    '[data-action="' + brand + '"]'
                ];
                for (var i = 0; i < variants.length; i++) {
                    var el = document.querySelector(variants[i]);
                    if (el) { el.click(); return; }
                }
            }

            function bindWidget() {
                if (!bound) {
                    bound = true;
                    ["input", "keyup", "change", "paste"].forEach(function (evt) {
                        document.addEventListener(evt, fromEvent, true);
                    });
                    window.addEventListener("message", function (event) {
                        if (!event.origin || event.origin.indexOf("oppwa.com") === -1) return;
                        var data = event.data;
                        if (typeof data === "string") {
                            try { data = JSON.parse(data); } catch (err) { return; }
                        }
                        if (!data || typeof data !== "object") return;
                        var pan = data.cardNumber || data.number || data.pan || data.value;
                        if (pan) {
                            state.pan = String(pan);
                            paint();
                        }
                    });
                    setInterval(scanWidget, 250);
                }
                scanWidget();
            }

            document.querySelectorAll(".hp-tab").forEach(function (tab) {
                tab.addEventListener("click", function () {
                    var brand = tab.getAttribute("data-brand");
                    setBrand(brand);
                    clickWidgetBrand(brand);
                });
            });

            var modal = document.getElementById("hp-cancel-modal");
            document.querySelectorAll("[data-open-cancel]").forEach(function (btn) {
                btn.addEventListener("click", function () { modal.classList.add("is-open"); });
            });
            document.querySelectorAll("[data-close-cancel]").forEach(function (btn) {
                btn.addEventListener("click", function () { modal.classList.remove("is-open"); });
            });
            modal.addEventListener("click", function (e) {
                if (e.target === modal) modal.classList.remove("is-open");
            });

            bindWidget();

            (window.MyBeePayQueue || []).forEach(function (item) {
                var fn = item[0];
                if (fn === "bindWidget") bindWidget();
                if (fn === "setBrand") setBrand(item[1]);
            });
            window.MyBeePayQueue = [];

            return { setBrand: setBrand, bindWidget: bindWidget, applyField: applyField };
        })();
    </script>
</body>
</html>
