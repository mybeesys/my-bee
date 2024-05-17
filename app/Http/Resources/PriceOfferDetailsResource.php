<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceOfferDetailsResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $extras = [];

        foreach ($this->resource->offerDetailsExtras as $extra){
            $extras[] = [
                'name' => $extra->display_name,
                'price' => number_format($extra->unit_price, currency_decimals(), '.', ''),
            ];
        }
        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->item->name,
            'unitPrice' => number_format($this->unit_price, currency_decimals(), '.', ''),
            'qty' => $this->qty,
            'discount' => number_format($this->discount, currency_decimals(), '.', ''),
            'tax' => number_format($this->tax, currency_decimals(), '.', ''),
            'extras' => $extras,
            'subTotal' => number_format($this->unit_price * $this->qty + $this->tax + collect($extras)->sum('price') - $this->discount ?? 0, currency_decimals(), '.', ''),
        ]);
    }
}
