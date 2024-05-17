<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemStock extends BaseResource
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
            'no' => $this->no,
            'warehouse' => new WarehouseResource($this->warehouse),
            'qtyIn' => $this->qty_in,
            'qtyMoved' => $this->qty_moved,
            'qtySold' => $this->qty_out,
            'qtyAvailable' => intval($this->available),
            'unitCost' => number_format($this->unit_cost, currency_decimals(), '.', ','),
            'unitRetailPrice' => number_format($this->item?->lastPrice?->retail_price, currency_decimals(), '.', ','),
            'addedBy' => new UserResource($this->user),
            'invoice' => new InvoiceResource($this->invoice),
        ]);
    }
}
