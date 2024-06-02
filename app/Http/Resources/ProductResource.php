<?php

namespace App\Http\Resources;

use App\Services\MediaService;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $discountPrice =  number_format(PricingService::instance()->getItemDiscountPrice($this->resource, null), currency_decimals(), '.', '');

        if(floatval($discountPrice) == 0)
            $discountPrice = null;

        return $this->filterFields([
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'images' => MediaService::mediaUrls($this->getMedia('images')),
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'securityStock' => $this->security_stock,
            'description' => $this->description,
            'published' => $this->published,
            'sort' => $this->sort,
            'price' => number_format(PricingService::instance()->getItemPrice($this->resource), currency_decimals(), '.', ''),
            'discountPrice' => $discountPrice,
            'qty' => StockService::instance()->getAvailableStock($this->resource),
            'calories' => $this->calories,
            'taxProfile' => new TaxProfileResource($this->taxProfile),
            'category' => new CategoryResource($this->category),
            'variantOptions' => VariantOptionsResource::collection($this->variantOptions),
            'variants' => AdvancedProductVariantResource::collection($this->variants),
            'extras' => AdvancedProductExtraResource::collection($this->extras),
            'stocks' => ItemStock::collection($this->stocks),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
        ]);
    }
}
