<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'unlimited_qty' => 'boolean',
        'variant_library_options_ids' => 'array',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariantOption::class);
    }

    public function getNameAttribute()
    {
        if (app()->getLocale() == "ar")
            return $this->name_ar;

        return $this->name_en;
    }

    public function isInventoryUnlimited(): bool
    {
        return $this->unlimited_qty;
    }

    public function getInventoryCountAttribute()
    {
        return $this->isInventoryUnlimited() ? 1000 : $this->qty ?? 0;
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
