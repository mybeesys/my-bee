<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemPrice extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
        'created_at' => 'datetime',
    ];

    //Product or ProductUnit
    public function item()
    {
        return $this->morphTo();
    }

    public function getHasDiscountAttribute():bool
    {
        return $this->discount_price and $this->discount_price > 0;
    }

    public function getRetailPriceAttribute()
    {
        if ($this->discount_price and $this->discount_price > 0)
            return $this->discount_price;
        return $this->price ?? 0;
    }
}
