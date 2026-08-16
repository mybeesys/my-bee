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
            'variantLibraryOptionsIds' => $this->variant_library_options_ids ?? [],
        ]);
    }
}
