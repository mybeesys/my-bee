<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetailsExtra extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function orderDetails(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderDetail::class, 'order_details_id');
    }

    public function productExtra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductExtra::class, 'product_extra_id');
    }
}
