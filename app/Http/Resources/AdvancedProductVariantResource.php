<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Http\Request;

class AdvancedProductVariantResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $pricingService = PricingService::instance();
        $price = number_format($pricingService->getOriginalPrice($this->resource), currency_decimals(), '.', '');
        $discountPrice = number_format($pricingService->getItemDiscountPrice($this->resource, null), currency_decimals(), '.', '');

        return $this->filterFields([
            'id' => $this->id,
            'image' => MediaService::mediaUrls($this->getMedia('images'), true),
            'productId' => $this->product_id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'calories' => $this->resource->product->calories,
            'new_item' => false,
            'should_remove' => false,
            'sku' => $this->sku,
            'price' => $price,
            'discountPrice' => $discountPrice,
            'variant_library_options_ids' => $this->variant_library_options_ids,
        ]);
    }
}
