<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductVariant;

class SupplyOrderDetailsResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $item = $this->item;
        $isVariant = $item instanceof ProductVariant;

        return $this->filterFields([
            'id' => $this->id,
            'name' => $item?->name,
            'qty' => (int) $this->qty,
            'productId' => $isVariant ? $item?->product_id : ($item instanceof Product ? $item->id : null),
            'productVariantId' => $isVariant ? $item?->id : null,
            'itemType' => $isVariant ? Product::$TYPE_VARIANTS : Product::$TYPE_BASIC,
        ]);
    }
}
