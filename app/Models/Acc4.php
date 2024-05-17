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

    public function acc3(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function getItemPricePriceForUnit($unit_id, $getPrice = false): mixed
    {
        $this->loadMissing('prices');

        if ($getPrice)
            return $this->prices->where('unit_id', $unit_id)->last()?->price;

        return $this->prices->where('unit_id', $unit_id)->last();
    }

    public static function asOptions(array $only_item_class = [], array $exclude_item_class = [], $useItemId = false, $with_code = false)
    {
        $options = [];

        $query = self::query()->with('item');

        if (count($only_item_class) > 0) {
            $query->whereIn('item_type', $only_item_class);
        }
        if (count($exclude_item_class) > 0) {
            $query->whereNotIn('item_type', $exclude_item_class)->orWhereNull('item_type');
        }

        $data = $query->get();

        foreach ($data as $acc4) {
            if ($acc4->item) {
                $id = $useItemId ? $acc4->item->id : $acc4->code;
                $name = $with_code ? $acc4->item->finance_name . " - $acc4->code" : $acc4->item->finance_name;
                $options[$id] = $name;
            } else {
                if (!$useItemId){
                    $name = $with_code ? $acc4->name . " - $acc4->code" : $acc4->name;
                    $options[$acc4->code] = $name;
                }
            }
        }

        return $options;
    }

}
