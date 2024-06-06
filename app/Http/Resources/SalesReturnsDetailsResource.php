<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnsDetailsResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'invoiceItemId' => $this->invoice_item_id,
            'name' => $this->invoiceItem->name,
            'qty' => $this->qty,
            'unitPrice' => number_format($this->invoiceItem->price, currency_decimals(), '.', ''),
            'subTotal' => number_format($this->invoiceItem->qty_returned * $this->invoiceItem->price, currency_decimals(), '.', ''),
        ]);
    }
}
