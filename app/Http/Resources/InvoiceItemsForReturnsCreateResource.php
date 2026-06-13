<?php

namespace App\Http\Resources;


class InvoiceItemsForReturnsCreateResource extends BaseResource
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
            'name' => $this->name,
            'unitPrice' => number_format($this->price, currency_decimals(), '.', ''),
            'maxQty' => $this->qty,
        ]);
    }
}
