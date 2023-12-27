<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends BaseModel implements HasMedia
{
    use HasFactory, HasFinancialAccount, InteractsWithMedia;

    public $finance = ['name' => 'name', 'acc3_code' => 1204]; //المخزون

    protected $guarded = [];


    public static string $TYPE_BASIC = "basic";
    public static string $TYPE_UNITS = "units";
    public static string $TYPE_VARIANTS = "variants";
    public static string $TYPE_SERVICE = "service";
    public static string $TYPE_DIGITAL = "digital";

//        public function scopeWithAvailable(Builder $query)
//        {
//            return $query->addSelect()
//        }

    protected $casts = [
        'attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

    public function rawMaterials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductRawMaterial::class);
    }

    public function costs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductProductionCost::class);
    }

    public function stocks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ItemStock::class, 'item');
    }

    public function lastPrice(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(ItemPrice::class, Acc4::class, 'item_id')->latest();
    }

    public function prices(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(ItemPrice::class, Acc4::class, 'item_id');
    }


    public function lastStock(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(ItemStock::class, 'item')->latest();
    }

    public function mainUnit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class, 'main_unit_id');
    }

    public function units()
    {
        return $this->hasMany(ProductUnit::class)->latest();
//        return $this->belongsToMany(Unit::class)->withPivot(['unit_count_from_main_unit']);
    }

    public function availableStocks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ItemStock::class, 'item')
            ->whereRaw("qty_in - qty_out > 0 order by greatest(qty_in - qty_out, 0)");
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

    public function takeStock(int $warehouse_id, int $requested_qty, $withPrice = false, $moveQty = false): array
    {
        $taken_from_stocks = [];

        $taken_qty = 0;

        $this->loadMissing('availableStocks');

        $stocks = $this->availableStocks->where('warehouse_id', $warehouse_id)->reverse();

        $availableQty = $stocks->sum(function ($i) {
            return $i->available;
        });

        if ($requested_qty > $availableQty) {
            fns()->sendDanger('Item Out of stock!');
            throw new \Exception("Requested quantity ($requested_qty) of ($this->name) is unavailable");
        }

        //req = 15, ava = 10

        //f-loop taken_qty = 0: requested_qty = 22||| 15
        //s-
        foreach ($stocks as $stock) {
            if ($taken_qty < $requested_qty) {
                //0                    320           100                 320             -   0      ? 100
                // 100                  320           300                 320                100     ? 300
                $take_qty_from_stock = min(($requested_qty - $taken_qty), $stock->available);

                if ($take_qty_from_stock > $stock->available)
                    throw new \Exception('Failed while decrementing stock');

                if ($moveQty) {
                    ItemStock::where('id', $stock->id)->increment('qty_moved', $take_qty_from_stock);
                } else {
                    ItemStock::where('id', $stock->id)->increment('qty_out', $take_qty_from_stock);
                }

                $taken_qty = $taken_qty + $take_qty_from_stock;

                if ($withPrice) {
                    $taken_from_stocks[] = [
                        'stock_id' => $stock->id,
                        'taken_from_stock' => $take_qty_from_stock,
                        'unit_cost_sdg' => $stock->unit_cost_sdg,
                        'unit_cost_usd' => $stock->unit_cost_usd,
                    ];

                } else {
                    $taken_from_stocks[] = $stock->id;
                }
            }

            $this->refresh();

        }

        return $taken_from_stocks;
    }

    public function hasAvailableQty(int $qty, $warehouse_id = null, $throw_exception = false): bool
    {
        $this->loadMissing(['availableStocks']);

        $availableQty = 0;

        if ($warehouse_id) {
            $warehouseName = Warehouse::find($warehouse_id)->name;

            $availableQty = $this->availableStocks->where('warehouse_id', $warehouse_id)->sum(function ($i) {
                return $i->available;
            });
        } else {
            $availableQty = $this->availableStocks->sum(function ($i) {
                return $i->available;
            });
        }

        $rs = $availableQty >= $qty;

        if ($throw_exception and $rs === false)
            throw new \Exception("Requested quantity ($qty) of ($this->name) is unavailable" . $warehouse_id == null ? "" : " in warehouse ($warehouseName)");


        return $rs;
    }

    public function addRawMaterial(Product $rawMaterial, $qty, $price_per_unit, $description = null): Model|RawMaterial
    {
        if ($rawMaterial->raw == false)
            throw new \Exception("Item is not raw material");

        return RawMaterial::updateOrCreate(
            [
                'product_id' => $this->id,
                'raw_material_id' => $rawMaterial->id,
            ],
            [
                'product_id' => $this->id,
                'raw_material_id' => $rawMaterial->id,
                'qty' => $qty,
                'price_per_unit' => $price_per_unit,
                'description' => $description,

            ]
        );
    }


    //old deprecated
//    public function unitsAsOptions($onlyHasAvailableStock = false, $onlyPriced = false): array
//    {
//        $this->loadMissing(['units.unit', 'prices', 'availableStocks']);
//
//        $options = [];
//
//        //add main unit
//        $options[$this->main_unit_id] = $this->mainUnit->name;
//
//        foreach ($this->units as $productUnit) {
//
//            if ($onlyHasAvailableStock and $this->availableStocks->where('unit_id', $productUnit->unit_id)->last() == null)
//                continue;
//
//            if ($onlyPriced and $this->prices->where('unit_id', $productUnit->unit_id)->last() == null)
//                continue;
//
//            $options[$productUnit->unit_id] = $productUnit->unit->name;
//        }
//
//        return $options;
//    }


    public function unitsAsOptions($onlyHasAvailableStock = false, $onlyPriced = false): array
    {
        $this->loadMissing(['units.unit', 'prices', 'availableStocks']);

        $options = [];

        //add main unit
        $options[$this->main_unit_id] = $this->mainUnit->name;

        foreach ($this->units as $productUnit) {

            if ($onlyHasAvailableStock and $productUnit->qty == 0)
                continue;

            $options[$productUnit->unit_id] = $productUnit->unit->name;
        }

        return $options;
    }

    //under construct
    public static function groupedAsOptions($published = true, $onlyHasAvailableStock = false): array
    {
        $products = Product::with(['variants', 'units'])
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

    public function variantOptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariantOption::class);
    }

    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
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

    public function isInventoryUnlimited(): bool
    {
        return $this->unlimited_qty;
    }

    public function getInventoryStatementForPanel(): string
    {
        if ($this->type === Product::$TYPE_BASIC) {
            if ($this->isInventoryUnlimited())
                return __('fields.unlimited_qty');
            return $this->qty;
        }
        if ($this->type === Product::$TYPE_VARIANTS) {
            return __('fields.variants');
        }
        if ($this->type === Product::$TYPE_UNITS) {
        }
        if ($this->type === Product::$TYPE_SERVICE) {
        }
        if ($this->type === Product::$TYPE_DIGITAL) {
        }
        return false;
    }

    public function getInventoryCountAttribute()
    {
        return $this->isInventoryUnlimited() ? 1000 : $this->qty ?? 0;
    }

    public function setPriceAttribute($value)
    {
        return $this->attributes['price'] = $value ?? 0;
    }

    public function setQtyAttribute($value)
    {
        return $this->attributes['qty'] = $value ?? 0;
    }
}
