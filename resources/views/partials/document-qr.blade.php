@php
    $qrPayload = $qrPayload ?? null;
    $qrDataUri = $qrDataUri ?? null;
    $qrKind = $qrKind ?? null;
    $qrImageId = $qrImageId ?? 'document-qr-image';
    $qrLabel = $qrLabel ?? match ($qrKind) {
        \App\Services\InvoiceZatcaQrService::KIND_ZATCA => __('fields.subscription_invoice_qr_hint'),
        \App\Services\InvoiceZatcaQrService::KIND_DOCUMENT => __('fields.invoice_qr_document_hint'),
        default => __('fields.vat'),
    };
@endphp

@if($qrPayload)
    <div class="meta-qr" data-qr-kind="{{ $qrKind }}">
        <div class="meta-qr__frame">
            @if($qrDataUri)
                <img
                    src="{{ $qrDataUri }}"
                    alt="{{ $qrLabel }}"
                    id="{{ $qrImageId }}"
                    onerror="window.renderDocumentQrFallback && window.renderDocumentQrFallback('{{ $qrImageId }}')"
                >
            @else
                <img
                    src=""
                    alt="{{ $qrLabel }}"
                    id="{{ $qrImageId }}"
                    width="120"
                    height="120"
                >
            @endif
        </div>
        <span class="meta-qr__label">{{ $qrLabel }}</span>
    </div>
@endif
