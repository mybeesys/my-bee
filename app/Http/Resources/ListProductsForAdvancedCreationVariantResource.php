<?php

namespace App\Http\Resources;

class ListProductsForAdvancedCreationVariantResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'productId' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'suggestedPrice' => $this->lastPrice
                ? number_format((float) \App\Services\PricingService::instance()->getRetailPrice($this->resource, 0), currency_decimals(), '.', '')
                : null,
            'variantLibraryOptionsIds' => $this->variant_library_options_ids ?? [],
        ]);
    }
}
