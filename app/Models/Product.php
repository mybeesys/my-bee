<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use App\Traits\HasPrice;
use App\Traits\HasStock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends BaseModel implements HasMedia
{
    use HasFactory, HasFinancialAccount, InteractsWithMedia, HasPrice, HasStock;

    public $finance = ['name' => 'name', 'acc3_code' => 1204]; //المخزون

    protected $guarded = [];

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static string $TYPE_BASIC = "basic";
    public static string $TYPE_VARIANTS = "variants";


    public function scopeTypeBasic(Builder $query): Builder
    {
        return $query->where('type', self::$TYPE_BASIC);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    //only use it for stocks relation manager to also display units stock
    public function allStocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemStock::class);
    }

    public function getFinanceNameAttribute()
    {
        if ($this->barcode) {
            return $this->name;
        }
        return $this->name;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function taxProfile()
    {
        return $this->belongsTo(TaxProfile::class);
    }

    public function extras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductExtra::class);
    }


    public static function asOptions($onlyHasAvailableStock = false, $onlyPriced = false, $id = "acc4_code"): array
    {
        $data = self::with(['acc4', 'lastPrice', 'availableStocks', 'stocks'])->get();

        //->reorder('DESC');
//            dd($data->first()->availableStocks->last());
        $options = [];
        foreach ($data as $product) {

            if ($onlyHasAvailableStock and $product->availableStocks->last() == null)
                continue;

            if ($onlyPriced and $product->lastPrice == null)
                continue;


            $options[$id == "acc4_code" ? $product->acc4->code : $product->id] = $product->finance_name;
        }
        return $options;
    }


    //under construct
    public static function groupedAsOptions($published = true, $onlyHasAvailableStock = false): array
    {
        $products = Product::with(['variants'])
            ->where('published', $published)
            ->latest()
            ->get()
            ->groupBy('type')
            ->map(fn($type) => $type->pluck('name', 'id'))
//            ->map(function ($items) {
//                return $items->groupBy(function ($item) {
//                    return $item->type;
//                })->map(function ($type) {
//                    return $type . "1";
//                });
//            })
            ->toArray();

        return $products;

        $options = [];

//        foreach ($products as $product) {
//
//            if($product->type)
//            if ($onlyHasAvailableStock and $this->availableStocks->where('unit_id', $productUnit->unit_id)->last() == null)
//                continue;
//
//            if ($onlyPriced and $this->prices->where('unit_id', $productUnit->unit_id)->last() == null)
//                continue;
//
//            $options[$productUnit->unit_id] = $productUnit->unit->name;
//        }

        return $options;
    }

    public function getPrice($product_variant_id = null, $unit_id = null)
    {
        if ($product_variant_id) {
            $this->loadMissing('variants');
            return $this->variants->filter(function ($item) use ($product_variant_id) {
                return $item->id == $product_variant_id;
            })->first()?->retail_price;
        }

        if ($unit_id) {
            if ($unit_id == $this->main_unit_id) {
                if ($this->discount_price and $this->discount_price > 0) {
                    return $this->discount_price;
                }
                return 0;
            }
            return $this->units->filter(function ($item) use ($unit_id) {
                return $item->unit_id == $unit_id;
            })->first()?->retail_price;
        }

        if ($this->discount_price and $this->discount_price > 0) {
            return $this->discount_price;
        }

        return $this->price ?? 0;
    }

    public function variantOptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariantOption::class);
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
