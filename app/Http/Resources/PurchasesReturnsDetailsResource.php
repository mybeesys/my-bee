<?php

namespace App\Http\Resources;

class PurchasesReturnsDetailsResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'invoiceItemId' => $this->invoice_item_id,
            'name' => $this->invoiceItem?->name,
            'qty' => (float) $this->qty,
            'price' => (float) $this->price,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'transactionCompleted' => (bool) $this->transaction_completed,
            'unitPrice' => $this->invoiceItem
                ? number_format((float) $this->invoiceItem->price, currency_decimals(), '.', '')
                : null,
        ]);
    }
}
