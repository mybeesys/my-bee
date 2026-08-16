<?php

namespace App\Http\Resources;

use App\Models\Invoice;

class SupplierPurchaseInvoiceListResource extends BaseResource
{
    /**
     * Compact purchase invoice row matching the supplier view invoices tab.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;
        $invoiceTotal = (float) $invoice->getItemsCost(true, true, true);
        $paidAmount = (float) $invoice->total_paid;

        return $this->filterFields([
            'id' => $invoice->id,
            'no' => $invoice->no,
            'status' => $invoice->status,
            'settlementStatusKey' => $invoice->settlement_status_key,
            'settlementStatus' => $invoice->payment_status,
            'date' => $invoice->date?->format('Y-m-d'),
            'paidAmount' => round($paidAmount, 2),
            'unpaidAmount' => round((float) $invoice->total_unpaid, 2),
            'invoiceTotal' => round($invoiceTotal, 2),
            'currency' => main_currency_iso_code(),
            'isPaid' => (bool) $invoice->paid,
            'hasPurchaseReturn' => (int) ($invoice->purchases_returns_count ?? 0) > 0,
            'purchasesReturnsCount' => (int) ($invoice->purchases_returns_count ?? 0),
            'paymentVoucherId' => $invoice->relationLoaded('paymentVoucher')
                ? $invoice->paymentVoucher?->id
                : null,
        ]);
    }
}
