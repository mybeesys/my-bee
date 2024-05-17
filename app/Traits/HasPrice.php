<?php

namespace App\Traits;


use App\Models\ItemPrice;

trait HasPrice
{
    public function prices(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ItemPrice::class, 'item');
    }

    public function lastPrice(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(ItemPrice::class, 'item')->latestOfMany();
    }
}
