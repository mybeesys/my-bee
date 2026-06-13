<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tax_profile_data' => 'array',
    ];

    public function item()
    {
        return $this->morphTo();
    }

    public function orderDetailsExtras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderDetailsExtra::class, 'order_details_id');
    }
}
