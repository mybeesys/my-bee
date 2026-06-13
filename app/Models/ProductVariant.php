<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use App\Traits\HasPrice;
use App\Traits\HasStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProductVariant extends BaseModel implements HasMedia
{
    use HasFactory, HasFinancialAccount, HasPrice, HasStock, InteractsWithMedia;

    protected $guarded = [];

    public $finance = ['name' => 'finance_name', 'acc3_code' => 1204]; //المخزون

    protected $casts = [
        'unlimited_qty' => 'boolean',
        'variant_library_options_ids' => 'array',
    ];

    public function getFinanceNameAttribute()
    {
        return $this->product->name ." ". $this->name;
    }

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }
}
