<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceOfferDetails extends BaseModel
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

    public function priceOffer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PriceOffer::class);
    }

    public function offerDetailsExtras(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PriceOfferDetailsExtras::class, 'price_offer_details_id');
    }

    public function getExtrasTotalAttribute()
    {
        $total = 0;
        foreach ($this->offerDetailsExtras as $offerDetailsExtra) {
            $total += $offerDetailsExtra->unit_price * $this->qty;

        }
        return $total;
    }
}
