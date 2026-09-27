@props([
    'brands' => ['MADA', 'VISA', 'MASTER'],
])

<div {{ $attributes->class(['order-summary__brands']) }}>
    @foreach ($brands as $brand)
        @php $code = strtoupper((string) $brand); @endphp
        <span class="order-summary__brand" title="{{ $code }}">
            @if ($code === 'MADA')
                <svg viewBox="0 0 52 20" class="order-summary__brand-svg" aria-hidden="true">
                    <rect width="52" height="20" rx="4" fill="#0B6A3B"/>
                    <text x="26" y="13.5" text-anchor="middle" fill="#fff" font-size="8" font-weight="800" font-family="Arial, sans-serif">mada</text>
                </svg>
            @elseif ($code === 'VISA')
                <svg viewBox="0 0 52 20" class="order-summary__brand-svg" aria-hidden="true">
                    <rect width="52" height="20" rx="4" fill="#1A1F71"/>
                    <text x="26" y="13.5" text-anchor="middle" fill="#fff" font-size="9" font-weight="800" font-family="Arial, sans-serif" letter-spacing="0.6">VISA</text>
                </svg>
            @elseif (in_array($code, ['MASTER', 'MASTERCARD', 'MC'], true))
                <svg viewBox="0 0 52 20" class="order-summary__brand-svg" aria-hidden="true">
                    <rect width="52" height="20" rx="4" fill="#111827"/>
                    <circle cx="22" cy="10" r="6" fill="#EB001B"/>
                    <circle cx="30" cy="10" r="6" fill="#F79E1B"/>
                    <path d="M26 5.6a6 6 0 0 1 0 8.8 6 6 0 0 1 0-8.8z" fill="#FF5F00"/>
                </svg>
            @else
                <span class="order-summary__brand-fallback">{{ $code }}</span>
            @endif
        </span>
    @endforeach
</div>
