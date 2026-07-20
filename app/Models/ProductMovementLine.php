<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMovementLine extends Model
{
    protected $table = 'invoice_items';

    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'created_at' => 'datetime',
        'qty' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'price' => 'float',
        'sub_total' => 'float',
    ];
}
