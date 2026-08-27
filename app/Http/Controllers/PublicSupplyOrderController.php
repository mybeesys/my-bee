<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SupplyOrder;
use App\Services\InvoiceZatcaQrService;
use Illuminate\View\View;

class PublicSupplyOrderController extends Controller
{
    public function show(string $slug, string $no): View
    {
        $supplyOrder = $this->resolveSupplyOrder($slug, $no);
        $documentUrl = route('public.supply-order.show', [
            'slug' => $supplyOrder->tenant->slug,
            'no' => $supplyOrder->no,
        ]);
        $qrService = InvoiceZatcaQrService::instance();

        return view('supply-orders.public', [
            'supplyOrder' => $supplyOrder,
            'tenant' => $supplyOrder->tenant,
            'settings' => $this->tenantSettings($supplyOrder->tenant_id),
            'qrPayload' => $documentUrl,
            'qrDataUri' => $qrService->documentQrDataUri($documentUrl),
            'qrKind' => InvoiceZatcaQrService::KIND_DOCUMENT,
        ]);
    }

    protected function resolveSupplyOrder(string $slug, string $no): SupplyOrder
    {
        return SupplyOrder::query()
            ->where('no', $no)
            ->whereHas('tenant', fn ($query) => $query->where('slug', $slug))
            ->with([
                'tenant.media',
                'supplier',
                'details.item',
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
