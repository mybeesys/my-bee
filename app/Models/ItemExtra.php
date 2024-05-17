<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemExtra extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    //wrong?
    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productsExtras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductExtra::class, 'item_extra_id');
    }
}
