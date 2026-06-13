<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use App\Traits\HasPrice;
use App\Traits\HasStock;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductUnit extends BaseModel
{
    use HasFactory, HasFinancialAccount, HasPrice, HasStock;

    public $finance = ['name' => 'name', 'acc3_code' => 1204]; //المخزون

    protected $guarded = [];

    protected $table = "product_unit";

    public function getFinanceNameAttribute()
    {
        return $this->product->name ." ". $this->unit->name;
    }

    public function getNameAttribute()
    {
        return $this->product->name ." ". $this->unit->name;
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

}
