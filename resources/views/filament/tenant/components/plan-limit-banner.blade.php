@php
    $usage = subscription_limit_usage($type ?? 'sales_invoices');
@endphp

@if ($usage)
    <div class="plan-limit-banner" role="alert" aria-live="polite">
        <div class="plan-limit-banner__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                <path fill-rule="evenodd" d="M9.401 3.003c1.155-.994 2.945-.994 4.1 0l7.395 6.363c1.152.989.375 2.634-1.05 2.634H3.056c-1.425 0-2.203-1.645-1.05-2.634L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd" />
            </svg>
        </div>

        <div class="plan-limit-banner__content">
            <div class="plan-limit-banner__header">
                <h3 class="plan-limit-banner__title">{{ $usage['title'] }}</h3>
                <span class="plan-limit-banner__badge">{{ $usage['used'] }} / {{ $usage['max'] }}</span>
            </div>

            <p class="plan-limit-banner__body">{{ $usage['body'] }}</p>
            <p class="plan-limit-banner__hint">{{ $usage['hint'] }}</p>

            <div class="plan-limit-banner__progress" aria-hidden="true">
                <div class="plan-limit-banner__progress-bar" style="width: {{ number_format($usage['percent'], 0) }}%"></div>
            </div>
        </div>

        <div class="plan-limit-banner__actions">
            <a href="{{ $upgradeUrl }}" class="plan-limit-banner__cta">
                {{ $usage['upgrade_label'] }}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </a>
        </div>
    </div>
@endif
