<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailsExtraResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'name' => $this->display_name,
            'price' => number_format($this->unit_price, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
        ]);
    }
}
