<?php


namespace App\Services;


use App\Models\Category;
use App\Models\ItemPrice;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductService
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

    public static function asOptions($onlyHasAvailableStock = false, $onlyPriced = false, $withUnitsAsOptions = false, $includeMainUnit = true)
    {
        $options = [];
        foreach (Product::with(['stocks', 'prices', 'lastPrice', 'units.prices', 'units.lastPrice', 'availableStocks', 'acc4'])->has('acc4')->get() as $product) {

            if($onlyHasAvailableStock){
                if($product->type === Product::$TYPE_BASIC and $product->availableStocks->isEmpty())
                    continue;
                if($product->type === Product::$TYPE_UNITS and $product->units->pluck('stocks')->flatten()->sum('available') == 0)
                    continue;
            }

            if($onlyPriced){
                if($product->type === Product::$TYPE_BASIC and $product->lastPrice == null)
                    continue;
            }

            $options[$product->id] = $product->name;

            if ($withUnitsAsOptions)
                foreach ($product->units->where('main', $includeMainUnit) as $productUnit) {
                    $name = $product->name . " - " . $productUnit->unit->name;
                    $options["$product->id-$productUnit->unit_id"] = $name;
                }
        }

        return $options;
    }

}
