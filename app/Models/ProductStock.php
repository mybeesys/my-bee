<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stock()
    {
        return $this->belongsTo(ProductStock::class);
    }

    public function getProfitPercent()
    {
        return ( ($this->retail_price_sdg - $this->cost_per_item_sdg) / $this->cost_per_item_sdg) * 100;
    }
}
