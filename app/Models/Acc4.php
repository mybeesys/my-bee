<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Acc4 extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "acc4";

    protected $primaryKey = 'code';

    public function getFullNameAttribute()
    {
        return $this->name . ' - ' . $this->code;
    }

    public function acc3Code()
    {
        return $this->belongsTo(Acc3::class, 'acc3_code');
    }

    public function item()
    {
        return $this->morphTo();
    }

    public function scopeProduct(Builder $query)
    {
        return $query->where('item_type', Product::class);
    }

    public function prices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemPrice::class, 'acc4_code');
    }

    public function lastPrice(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ItemPrice::class, 'acc4_code')->latest();
    }

    public function getItemPricePriceForUnit($unit_id, $getPrice = false):mixed
    {
        $this->loadMissing('prices');

        if($getPrice)
            return $this->prices->where('unit_id', $unit_id)->last()?->price;

        return $this->prices->where('unit_id', $unit_id)->last();
    }

    public static function asOptions($item_class, $onlyHasAvailableStock = false, $onlyPriced = false, $useItemId = false, $withUnitsAsOptions = false)
    {
        $data = self::with(['item.units.unit'])->where('item_type', $item_class)->get();
        $options = [];
        foreach ($data as $acc4) {
            if ($acc4->item) {
                if ($item_class == Product::class) {

//                        dd($acc4->item);
//                        $acc4->item->load('lastPrice', 'availableStock');;

                    if ($onlyHasAvailableStock and $acc4->item->availableStock == null)
                        continue;

                    if ($onlyPriced and $acc4->item->lastPrice == null)
                        continue;

                }

                $id = $useItemId ? $acc4->item->id : $acc4->code;

                $options[$id] = $acc4->item->finance_name;

                if($withUnitsAsOptions)
                    foreach ($acc4->item->units as $productUnit)
                    {
                        $name = $acc4->item->name . " - " . $productUnit->unit->name ." - ". $productUnit->barcode;
                        $options["$id-$productUnit->unit_id"] = $name;
                    }
//                if ($useItemId)
//                    $options[$acc4->item->id] = $acc4->item->finance_name;
//                else
//                    $options[$acc4->code] = $acc4->item->finance_name;
            }
        }

        return $options;
    }

}
