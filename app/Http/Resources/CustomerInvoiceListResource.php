<?php

namespace App\Http\Resources;

use App\Models\Invoice;

class CustomerInvoiceListResource extends BaseResource
{
    /**
     * Compact invoice row matching the customer view invoices tab.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->resource;
        $invoiceTotal = (float) $invoice->getItemsCost(true, true, true);
        $paidAmount = (float) $invoice->total_paid;
        $paidPercent = $invoiceTotal > 0 ? percent($paidAmount, $invoiceTotal) : 0;

        return $this->filterFields([
            'id' => $invoice->id,
            'no' => $invoice->no,
            'orderNo' => $invoice->order?->no,
            'status' => $invoice->status,
            'settlementStatusKey' => $invoice->settlement_status_key,
            'settlementStatus' => $invoice->payment_status,
            'date' => $invoice->date?->format('Y-m-d'),
            'paidAmount' => round($paidAmount, 2),
            'paidAmountPercent' => round((float) $paidPercent, 2),
            'unpaidAmount' => round((float) $invoice->total_unpaid, 2),
            'additionalCosts' => round((float) $invoice->getAdditionalCosts(), 2),
            'invoiceTotal' => round($invoiceTotal, 2),
            'currency' => main_currency_iso_code(),
            'isPaid' => (bool) $invoice->paid,
            'hasSalesReturn' => (int) ($invoice->sales_returns_count ?? 0) > 0,
            'salesReturnsCount' => (int) ($invoice->sales_returns_count ?? 0),
            'receiptVoucherId' => $invoice->relationLoaded('receiptVoucher')
                ? $invoice->receiptVoucher?->id
                : null,
        ]);
    }
}
