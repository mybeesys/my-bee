<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;

class AdvancedProductExtraResource extends BaseResource
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
            'productExtraID' => $this->extra->id,
            'name' => $this->extra->name,
            'price' => number_format(PricingService::instance()->getItemPrice($this->resource), currency_decimals(), '.', ''),
            'discountPrice' => number_format(PricingService::instance()->getItemDiscountPrice($this->resource), currency_decimals(), '.', ',') . " ",
        ]);
    }
}
