<?php

namespace App\Http\Controllers;

use App\Models\PriceOffer;
use App\Models\Setting;
use App\Services\InvoiceZatcaQrService;
use Illuminate\View\View;

class PublicPriceOfferController extends Controller
{
    public function show(string $slug, string $no): View
    {
        $priceOffer = $this->resolvePriceOffer($slug, $no);
        $documentUrl = route('public.price-offer.show', [
            'slug' => $priceOffer->tenant->slug,
            'no' => $priceOffer->no,
        ]);
        $qrService = InvoiceZatcaQrService::instance();

        return view('price-offers.public', [
            'priceOffer' => $priceOffer,
            'tenant' => $priceOffer->tenant,
            'settings' => $this->tenantSettings($priceOffer->tenant_id),
            'qrPayload' => $documentUrl,
            'qrDataUri' => $qrService->documentQrDataUri($documentUrl),
            'qrKind' => InvoiceZatcaQrService::KIND_DOCUMENT,
        ]);
    }

    protected function resolvePriceOffer(string $slug, string $no): PriceOffer
    {
        return PriceOffer::query()
            ->where('no', $no)
            ->whereHas('tenant', fn ($query) => $query->where('slug', $slug))
            ->with([
                'tenant.media',
                'customer',
                'details.item',
                'details.offerDetailsExtras.productExtra',
                'details.taxProfile',
                'services.type',
                'additionalCosts.type',
            ])
            ->firstOrFail();
    }

    /**
     * @return array<string, string|null>
     */
    protected function tenantSettings(int $tenantId): array
    {
        return Setting::query()
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->all();
    }
}
