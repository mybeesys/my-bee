<?php

namespace App\Models;

use App\Traits\HasFinancialAccount;
use App\Traits\HasPrice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductExtra extends BaseModel
{
    use HasFactory, HasFinancialAccount, HasPrice;

    protected $guarded = [];

    public $finance = ['name' => 'name', 'acc3_code' => 1204]; //المخزون

    protected $table = "product_extra";

    public function getFinanceNameAttribute()
    {
        return $this->product->name ." ". $this->extra->name;
    }

    public function getNameAttribute()
    {
        return $this->extra->name;
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function extra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ItemExtra::class, 'item_extra_id');
    }
}
