<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

class InvoiceZatcaQrService
{
    public const KIND_ZATCA = 'zatca';

    public const KIND_DOCUMENT = 'document';

    public static function instance(): self
    {
        return new self();
    }

    /**
     * @return array{subtotal: float, vat: float, total: float, has_vat: bool}
     */
    public function vatSummary(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'additionalCosts', 'services']);

        $total = round((float) $invoice->getItemsCost(true, true, true), 2);
        $vat = round((float) $invoice->getTaxesAsAmount(), 2);
        $subtotal = round(max(0, $total - $vat), 2);

        return [
            'subtotal' => $subtotal,
            'vat' => $vat,
            'total' => $total,
            'has_vat' => $vat > 0,
        ];
    }

    /**
     * Resolve seller TRN from tenant profile first, then tenant settings.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function resolveSellerTrn(?Tenant $tenant, ?array $settings = null): string
    {
        $candidates = [
            trim((string) ($tenant?->trn ?? '')),
            trim((string) ($settings['company.trn'] ?? '')),
        ];

        foreach ($candidates as $trn) {
            if ($trn !== '') {
                return $trn;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function tlvBase64(Invoice $invoice, Tenant $tenant, string $sellerName, ?array $settings = null): ?string
    {
        $trn = $this->resolveSellerTrn($tenant, $settings);
        $sellerName = trim($sellerName);

        if ($trn === '' || $sellerName === '') {
            return null;
        }

        $summary = $this->vatSummary($invoice);

        if ($summary['total'] <= 0) {
            return null;
        }

        $timestamp = Carbon::parse($invoice->date ?? now())
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');

        return $this->encodeTlv([
            1 => $sellerName,
            2 => $trn,
            3 => $timestamp,
            4 => number_format($summary['total'], 2, '.', ''),
            5 => number_format($summary['vat'], 2, '.', ''),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $settings
     * @return array{qrPayload: ?string, qrDataUri: ?string, qrKind: ?string, trn: string}
     */
    public function buildInvoiceQr(
        Invoice $invoice,
        Tenant $tenant,
        string $sellerName,
        ?array $settings = null,
        ?string $documentUrl = null,
    ): array {
        $trn = $this->resolveSellerTrn($tenant, $settings);
        $payload = $this->tlvBase64($invoice, $tenant, $sellerName, $settings);
        $kind = $payload !== null ? self::KIND_ZATCA : null;

        if ($payload === null && filled($documentUrl)) {
            $payload = $documentUrl;
            $kind = self::KIND_DOCUMENT;
        }

        return [
            'qrPayload' => $payload,
            'qrDataUri' => $this->normalizeDataUri($this->qrDataUriFromPayload($payload)),
            'qrKind' => $kind,
            'trn' => $trn,
        ];
    }

    public function qrDataUri(Invoice $invoice, Tenant $tenant, string $sellerName, ?array $settings = null): ?string
    {
        return $this->qrDataUriFromPayload($this->tlvBase64($invoice, $tenant, $sellerName, $settings));
    }

    public function subscriptionTlvBase64(Subscription $subscription, string $sellerName, string $trn): ?string
    {
        $trn = trim($trn);
        $sellerName = trim($sellerName);

        if ($trn === '' || $sellerName === '') {
            return null;
        }

        $total = round((float) ($subscription->price ?? 0), 2);

        if ($total <= 0) {
            return null;
        }

        $vat = round((float) ($subscription->tax_amount ?? 0), 2);
        $timestamp = Carbon::parse($subscription->paid_at ?? $subscription->start_date ?? now())
            ->utc()
            ->format('Y-m-d\TH:i:s\Z');

        return $this->encodeTlv([
            1 => $sellerName,
            2 => $trn,
            3 => $timestamp,
            4 => number_format($total, 2, '.', ''),
            5 => number_format($vat, 2, '.', ''),
        ]);
    }

    /**
     * @return array{qrPayload: ?string, qrDataUri: ?string, qrKind: ?string}
     */
    public function buildSubscriptionQr(
        Subscription $subscription,
        string $sellerName,
        string $trn,
        ?string $documentUrl = null,
    ): array {
        $payload = $this->subscriptionTlvBase64($subscription, $sellerName, $trn);
        $kind = $payload !== null ? self::KIND_ZATCA : null;

        if ($payload === null && filled($documentUrl)) {
            $payload = $documentUrl;
            $kind = self::KIND_DOCUMENT;
        }

        return [
            'qrPayload' => $payload,
            'qrDataUri' => $this->normalizeDataUri($this->qrDataUriFromPayload($payload)),
            'qrKind' => $kind,
        ];
    }

    public function subscriptionQrDataUri(Subscription $subscription, string $sellerName, string $trn): ?string
    {
        return $this->qrDataUriFromPayload(
            $this->subscriptionTlvBase64($subscription, $sellerName, $trn),
        );
    }

    public function documentQrDataUri(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        return $this->normalizeDataUri($this->qrDataUriFromPayload($url));
    }

    public function qrDataUriFromPayload(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        try {
            if (extension_loaded('gd') && class_exists(QRGdImagePNG::class)) {
                return (new QRCode($this->pngOptions()))->render($payload);
            }

            return (new QRCode($this->svgOptions()))->render($payload);
        } catch (Throwable $exception) {
            report($exception);

            try {
                return (new QRCode($this->svgOptions()))->render($payload);
            } catch (Throwable $fallbackException) {
                report($fallbackException);

                return null;
            }
        }
    }

    public function normalizeDataUri(?string $uri): ?string
    {
        if ($uri === null || $uri === '') {
            return null;
        }

        if (str_starts_with($uri, 'data:')) {
            return $uri;
        }

        return 'data:image/png;base64,' . $uri;
    }

    protected function pngOptions(): QROptions
    {
        return new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => 5,
            'quietzoneSize' => 2,
            'addQuietzone' => true,
        ]);
    }

    protected function svgOptions(): QROptions
    {
        return new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => 5,
            'quietzoneSize' => 2,
            'addQuietzone' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function encodeTlv(array $tags): string
    {
        $tlv = '';

        foreach ($tags as $tag => $value) {
            $encoded = mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8');
            $length = strlen($encoded);

            if ($length > 255) {
                throw new \InvalidArgumentException("TLV tag {$tag} exceeds 255 bytes.");
            }

            $tlv .= chr((int) $tag) . chr($length) . $encoded;
        }

        return base64_encode($tlv);
    }
}
