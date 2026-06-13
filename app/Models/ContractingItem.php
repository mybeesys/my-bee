<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractingItem extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function category()
    {
        return $this->belongsTo(ContractingItemCategory::class, 'contracting_item_category_id');
    }

    public function smallUnit()
    {
        return $this->belongsTo(Unit::class, 'small_unit_price');
    }

    public function largeUnit()
    {
        return $this->belongsTo(Unit::class, 'large_unit_price');
    }
}
