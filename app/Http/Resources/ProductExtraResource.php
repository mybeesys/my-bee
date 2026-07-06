<?php

namespace App\Http\Resources;

use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductExtraResource extends BaseResource
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
            'name' => $this->extra?->name ?? '-',
            'hasDiscount' => PricingService::instance()->hasDiscount($this->resource),
            'originalPrice' => number_format(PricingService::instance()->getOriginalPrice($this->resource), currency_decimals(), '.', ''),
            'price' => number_format(PricingService::instance()->getRetailPrice($this->resource), currency_decimals(), '.', ''),
            'originalPriceFormatted' => number_format(PricingService::instance()->getOriginalPrice($this->resource), currency_decimals(), '.', ',') . " ". main_currency_iso_code(),
            'priceFormatted' => number_format(PricingService::instance()->getRetailPrice($this->resource), currency_decimals(), '.', ',') . " " . main_currency_iso_code(),
            'inStock' => true,
        ]);
    }
}
