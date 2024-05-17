<?php


namespace App\Services;


use App\Models\Category;
use App\Models\ItemPrice;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    protected $tenant_id;

    public static function instance()
    {
        return new self();
    }

    public function tenant($tenant_id): self
    {
        $this->tenant_id = $tenant_id;
        return $this;
    }

    protected function getTenantId()
    {
        return $this->tenant_id ?? filament()->getTenant()->id;
    }

    protected function shouldAddNewPrice(Model $model, $unit_cost, $new_price, $new_discount): bool
    {
        if (!$model instanceof Product and !$model instanceof ProductUnit and !$model instanceof ProductExtra and !$model instanceof ProductVariant)
            throw new \Exception("Unknown item type");

        if (!$new_price) {
            return false;
        }

        $lastPrice = $model->lastPrice;

        return !$lastPrice or $lastPrice->unit_cost != $unit_cost or $lastPrice->price != $new_price or
            $lastPrice->discount_price != $new_discount;
    }

    public function addPrice(Model $item, $cost, $price, $discount_price = null): ?ItemPrice
    {
        $cost = is_number($cost) ? $cost : null;
        $price = is_number($price) ? $price : null;
        $discount_price = is_number($discount_price) ? $discount_price : null;

        if (!$item->acc4) {
            throw new \Exception("Item account not found.");
        }

        if (!$this->shouldAddNewPrice($item, $cost, $price, $discount_price))
            return null;

        return ItemPrice::create([
            'tenant_id' => self::getTenantId(),
            'item_id' => $item->id,
            'item_type' => get_class($item),
            'unit_cost' => $cost,
            'price' => $price,
            'discount_price' => $discount_price,
            'user_id' => auth()->id(),
            'date' => now(),
        ]);
    }

    public function getItemCost(?Model $model, $default = null)
    {
        return $model?->lastPrice?->unit_cost ?? $default;
    }

    public function getItemDiscountPrice(?Model $model, $default = null)
    {
        return $model?->lastPrice?->discount_price ?? $default;
    }

    public function getItemPrice(?Model $model, $default = null)
    {
        return $model?->lastPrice?->price ?? $default;
    }

    public function getItemsPrices($items)
    {
        $price = 0;

        foreach ($items as $item) {
            $price += $item?->lastPrice?->price ?? 0;
        }
        return $price;
    }

    public function getOriginalPrice(?Model $model, $default = 0)
    {
        return $model?->lastPrice?->price ?? $default;
    }

    public function getRetailPrice(?Model $model, $default = 0)
    {
        return $model?->lastPrice?->retail_price ?? $default;
    }

    public function getRetailPrices($items)
    {
        $price = 0;

        foreach ($items as $item) {
            $price += $item?->lastPrice?->retail_price ?? 0;
        }
        return $price;
    }

    public function hasDiscount(?Model $model, $default = false)
    {
        return $model?->lastPrice?->has_discount ?? $default;
    }

    public function getLastPrice(?Model $model, $default = null): ?ItemPrice
    {
        return $model?->lastPrice ?? $default;
    }

    public function getTaxAmount(Product $product, $price, int $qty)
    {
        $tax = 0;
        if ($product->taxProfile?->total_percentages) {
            $sub_total = $price * $qty;
            $tax = $sub_total * ($product->taxProfile->total_percentages / 100);
        }
        return $tax;
    }

    public function getTaxAmountFromProfile(TaxProfile $taxProfile, $price, int $qty)
    {
        $sub_total = $price * $qty;
        return $sub_total * ($taxProfile->total_percentages / 100);
    }

    public function getProductVariantsPriceRange(Product $product, $default = "-")
    {

        $min = $product->variants->pluck('prices')->min(function ($item) {
            return $item->min('price');
        });
        $max = $product->variants->pluck('prices')->max(function ($item) {
            return $item->max('price');
        });

        return number_format($min, currency_decimals(), '.', ',') . " - " . number_format($max, currency_decimals(), '.', ',');
    }
}
