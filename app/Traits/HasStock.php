<?php

namespace App\Traits;


use App\Models\ItemStock;

trait HasStock
{
    public function stocks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ItemStock::class, 'item');
    }

    public function lastStock(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(ItemStock::class, 'item')->latest();
    }

    public function availableStocks(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(ItemStock::class, 'item')
            ->whereRaw("qty_in - qty_out > 0 order by greatest(qty_in - qty_out, 0)");
    }
}
