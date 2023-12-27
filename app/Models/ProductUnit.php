<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $table = "product_unit";

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function getInventoryCountAttribute()
    {
        return $this->qty ?? 0;
    }

    public function getRetailPriceAttribute()
    {
        if ($this->discount_price and $this->discount_price > 0)
            return $this->discount_price;
        return $this->price ?? 0;
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
