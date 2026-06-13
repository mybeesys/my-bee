<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'name' => $this->name,
            'qty' => $this->qty,
            'price' => number_format($this->price, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'cancelled' => $this->cancelled,
//            'extras' => OrderDetailsExtraResource::collection($this->orderDetailsExtras),
            'extras' => OrderItemExtrasResource::collection($this->extras),
        ]);
    }
}
