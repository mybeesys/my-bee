<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Tenant;
use App\Services\CacheService;
use App\Services\MediaService;
use App\Services\PricingService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $pricingService = PricingService::instance();

        $originalPrice = null;
        $price = null;
        $discountPercent = null;
        $hasDiscount = null;
        $qty = null;
        $inStock = null;
        $inCart = null;

        if ($this->type == Product::$TYPE_BASIC) {
            $hasDiscount = $pricingService->hasDiscount($this->resource);
            $originalPrice = number_format($pricingService->getOriginalPrice($this->resource), currency_decimals(), '.', '');
            $price = number_format($pricingService->getRetailPrice($this->resource), currency_decimals(), '.', '');
            $qty = StockService::instance()->getAvailableStock($this->resource);
            $inCart = $this->itemInCart($this->resource, null);
            $inStock = $qty > 0;

            $tenant = get_tenant();

            if (!$tenant->store_enable_stock_tracking) {
                $inStock = true;
                $qty = 100;
            }
        }

        if ($this->type == Product::$TYPE_VARIANTS) {
            $price = $pricingService->getProductVariantsPriceRange($this->resource);
        }

        if ($hasDiscount and is_number($originalPrice) and is_number($price)) {
            $discountPercent = (int)number_format(percent($originalPrice - $price, $originalPrice), 0);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'images' => MediaService::mediaUrls($this->getMedia('images')),
            'variantsImages' => $this->getVariantsImages($this->resource),
            'updateVariantImageEverySeconds' => 3,
            'name' => $this->name,
            'sku' => $this->sku,
            'categoryId' => $this->category_id,
            'description' => $this->description,
            'calories' => $this->calories,
            'currency' => main_currency_iso_code(),
            'tax' => $pricingService->getTaxAmount($this->resource, is_number($price) ? $price : 0, 1),
            'hasDiscount' => $hasDiscount,
            'discountPercent' => $discountPercent,
            'originalPrice' => $originalPrice,
            'price' => $price,
            'qty' => $qty,
            'inCart' => $inCart,
            'inStock' => is_bool($inStock) ? $inStock : null,
            'outOfStock' => is_bool($inStock) ? !$inStock : null,
            'extras' => StoreProductExtraResource::collection($this->extras),
            'variantsOptions' => StoreProductVariantOptionResource::collection($this->variantOptions),
        ];
    }

    protected function itemInCart(Product $product, $product_variant_id = null): bool
    {
        if ($product_variant_id == null) {
            return collect($this->getCart()['items'] ?? [])->filter(fn($item) => $item['productId'] == $product->id)->first() != null;
        }

        return collect($this->getCart()['items'] ?? [])->filter(fn($item) => $item['productId'] == $product->id and $item['productVariantId'] == $product_variant_id)->first() != null;
    }

    protected function getCart(): array
    {
        $uuid = \request()->header('Store-UUID');
        return CacheService::instance()->get("cart@$uuid", [
            "subTotal" => 0.0,
            "subTotalFormatted" => "0.0 SAR",
            'tax' => 0,
            'taxFormatted' => "0.0 SAR",
            "items" => []
        ]);
    }

    protected function getVariantsImages(Product $product): array
    {
        $urls = [];
        foreach ($product->variants as $variant) {
            $urls = array_merge($urls, MediaService::mediaUrls($variant->getMedia('images')));
        }
        return $urls;
    }
}
