<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplyOrderDetails extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function item()
    {
        return $this->morphTo();
    }

    public function supplyOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SupplyOrder::class);
    }
}
