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

class StoreProductVariantResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $stockService = StockService::instance();
        $pricingService = PricingService::instance();

        $originalPrice = number_format($pricingService->getOriginalPrice($this->resource), currency_decimals(), '.', '');
        $price = number_format($pricingService->getRetailPrice($this->resource), currency_decimals(), '.', '');

        $qty = $stockService->getAvailableStock($this->resource);

        $inStock = $qty > 0;

        $tenant = get_tenant();

        if(!$tenant->store_enable_stock_tracking){
            $inStock = true;
            $qty = 100;
        }

        return $this->filterFields([
            'id' => $this->id,
            'productId' => $this->product_id,
            'name' => $this->name,
            'image' => MediaService::mediaUrls($this->getMedia('images'), true),
            'calories' => $this->resource->product->calories,
            'currency' => main_currency_iso_code(),
            'hasDiscount' => $pricingService->hasDiscount($this->resource),
            'discountPercent' => (int)number_format(percent($originalPrice - $price, $originalPrice), 0),
            'originalPrice' => $originalPrice,
            'price' => $price,
            'qty' => $qty,
            'inCart' => $this->itemInCart($this->resource->product, $this->id),
            'inStock' => $inStock,
            'outOfStock' => !$inStock,
            'sku' => $this->sku,
        ]);
    }

    protected function itemInCart(Product $product, $product_variant_id = null): bool
    {
        if($product_variant_id == null){
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
}
