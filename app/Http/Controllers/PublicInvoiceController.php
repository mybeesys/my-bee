<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\InvoicePdfService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicInvoiceController extends Controller
{
    public function show(string $uid): View
    {
        $invoice = $this->resolveInvoice($uid);

        return view('invoices.public', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'settings' => $this->tenantSettings($invoice->tenant_id),
            'pdfUrl' => route('public.invoice.pdf', ['uid' => $invoice->uid]),
        ]);
    }

    public function pdf(string $uid): Response
    {
        $invoice = $this->resolveInvoice($uid);

        if (request()->filled('lang')) {
            app()->setLocale(request()->query('lang'));
        }

        return (new InvoicePdfService())->stream($invoice);
    }

    protected function resolveInvoice(string $uid): Invoice
    {
        return Invoice::query()
            ->where('uid', $uid)
            ->where('temp', false)
            ->with([
                'tenant.media',
                'customer',
                'supplier',
                'representative',
                'items.product',
                'items.productVariant',
                'items.extras',
                'items.taxProfile',
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
