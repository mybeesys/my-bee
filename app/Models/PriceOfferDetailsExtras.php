<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOfferDetailsExtras extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['productExtra'];
    public function productExtra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductExtra::class, 'product_extra_id');
    }
}
