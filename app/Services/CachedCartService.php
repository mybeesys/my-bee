<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class CachedCartService
{
    protected ?string $key = null;


    public function __construct($key)
    {
        $this->key = $key;
    }

    public static function instance($key): self
    {
        return new self($key);
    }

    public function key($key)
    {
        $this->key = $key;
        return $this;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getCart($default = []): array
    {
        return CacheService::instance()->get($this->getKey(), $default);
    }

    public function addItem(): array
    {
        $uuid = \request()->header('Store-UUID');
        $store_slug = \request()->header('Store-Slug');

        $cartItems = self::getCart();

        $product = Product::findOrFail($request->product_id);

        $new_item = [
            'uuid' => $uuid,
            'id' => Str::random(8),
            'storeSlug' => $store_slug,
            'image' => null,
            'name' => null,
            'type' => null,
            'productId' => $product->id,
            'productVariantId' => null,
            'qty' => 1,
            'maxQty' => 1,
            'price' => 0,
            'priceFormatted' => 0,
            'createdAt' => now(),
            'updatedAt' => null,
        ];

        if ($product->type == Product::$TYPE_BASIC) {

            $exists = collect($cartItems)->where('productId', $product->id)->isNotEmpty();
            if ($exists)
                return $this->responder(__('fields.order_details_item_already_exists'), 400, [])->respond();

            $new_item['image'] = null;
            $new_item['name'] = $product->name;
            $new_item['type'] = 'basic';
            $new_item['price'] = PricingService::instance()->getRetailPrice($product);
            $new_item['priceFormatted'] = main_currency_iso_code() . " " . number_format(PricingService::instance()->getRetailPrice($product), currency_decimals(), '.', '');
            $new_item['maxQty'] = StockService::instance()->getAvailableStock($product);
        } elseif ($product->type == Product::$TYPE_VARIANTS) {
            $productVariant = self::getExistingVariantByCombination($product, $request->variants_options_ids ?? []);

            if (!$productVariant)
                return $this->responder(__('messages.api.retrieved'), 404, ['message' => 'Variant not found'])->respond();

            $exists = collect($cartItems)->where('productId', $product->id)->where('productVariantId', $productVariant->id)->isNotEmpty();
            if ($exists)
                return $this->responder(__('fields.order_details_item_already_exists'), 400, [])->respond();

            $new_item['image'] = null;
            $new_item['name'] = $productVariant->name;
            $new_item['type'] = 'variants';
            $new_item['productVariantId'] = $productVariant->id;
            $new_item['price'] = PricingService::instance()->getRetailPrice($productVariant);
            $new_item['priceFormatted'] = main_currency_iso_code() . " " . number_format(PricingService::instance()->getRetailPrice($productVariant), currency_decimals(), '.', '');
            $new_item['maxQty'] = StockService::instance()->getAvailableStock($productVariant);

        } else {
            throw new \Exception("Unknown product type");
        }

        $newCart = array_merge($cartItems, [$new_item]);

        CacheService::instance()->put($this->getKey(), $newCart);

        return $this->getCart();
    }
}
