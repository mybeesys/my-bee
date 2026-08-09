<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;

class InvoiceZatcaQrService
{
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

    public function tlvBase64(Invoice $invoice, Tenant $tenant, string $sellerName): ?string
    {
        $trn = trim((string) ($tenant->trn ?? ''));

        if ($trn === '') {
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

    public function qrDataUri(Invoice $invoice, Tenant $tenant, string $sellerName): ?string
    {
        return $this->qrDataUriFromPayload($this->tlvBase64($invoice, $tenant, $sellerName));
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

    public function subscriptionQrDataUri(Subscription $subscription, string $sellerName, string $trn): ?string
    {
        return $this->qrDataUriFromPayload(
            $this->subscriptionTlvBase64($subscription, $sellerName, $trn),
        );
    }

    public function qrDataUriFromPayload(?string $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        return (new QRCode($this->qrOptions()))->render($payload);
    }

    protected function qrOptions(): QROptions
    {
        return new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::H,
            'scale' => 6,
            'quietzoneSize' => 4,
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
            $encoded = mb_convert_encoding((string) $value, 'UTF-8');
            $length = strlen($encoded);

            if ($length > 255) {
                throw new \InvalidArgumentException("TLV tag {$tag} exceeds 255 bytes.");
            }

            $tlv .= chr((int) $tag) . chr($length) . $encoded;
        }

        return base64_encode($tlv);
    }
}
