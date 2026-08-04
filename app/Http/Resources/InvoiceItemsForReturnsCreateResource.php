<?php

namespace App\Http\Resources;

class InvoiceItemsForReturnsCreateResource extends BaseResource
{
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'invoiceItemId' => $this->id,
            'name' => $this->name,
            'unitPrice' => number_format((float) $this->price, currency_decimals(), '.', ''),
            'maxQty' => (float) $this->qty,
            'returnableQty' => (float) $this->qty,
        ]);
    }
}
