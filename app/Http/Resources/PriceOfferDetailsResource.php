<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductVariant;

class PriceOfferDetailsResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $item = $this->item;
        $isVariant = $item instanceof ProductVariant;
        $extras = [];

        foreach ($this->resource->offerDetailsExtras as $extra) {
            $extras[] = [
                'id' => $extra->id,
                'productExtraId' => $extra->product_extra_id,
                'name' => $extra->display_name,
                'price' => number_format($extra->unit_price, currency_decimals(), '.', ''),
            ];
        }

        return $this->filterFields([
            'id' => $this->id,
            'name' => $item?->name,
            'productId' => $isVariant ? $item?->product_id : ($item instanceof Product ? $item->id : null),
            'productVariantId' => $isVariant ? $item?->id : null,
            'itemType' => $isVariant ? Product::$TYPE_VARIANTS : Product::$TYPE_BASIC,
            'unitPrice' => number_format($this->unit_price, currency_decimals(), '.', ''),
            'qty' => (int) $this->qty,
            'discount' => number_format($this->discount, currency_decimals(), '.', ''),
            'tax' => number_format($this->tax, currency_decimals(), '.', ''),
            'taxProfileId' => $this->tax_profile_id,
            'extras' => $extras,
            'extrasTotal' => number_format($this->extras_total, currency_decimals(), '.', ''),
            'subTotal' => number_format($this->unit_price * $this->qty + collect($extras)->sum('price') - $this->discount ?? 0, currency_decimals(), '.', ''),
        ]);
    }
}
