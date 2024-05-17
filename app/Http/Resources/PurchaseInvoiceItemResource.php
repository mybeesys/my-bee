<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceItemResource extends BaseResource
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
            'taxProfileId' => $this->taxProfile?->id,
            'qty' => $this->qty,
            'discount' => number_format($this->discount, currency_decimals(), '.', ''),
            'tax' => number_format($this->tax, currency_decimals(), '.', ''),
            'price' => number_format($this->price, currency_decimals(), '.', ''),
            'subTotal' => number_format($this->subTotal, currency_decimals(), '.', ''),
            'canDelete' => $this->resource->invoice->status == "purchase_order",
            'user' => new UserResource($this->user),
        ]);
    }
}
