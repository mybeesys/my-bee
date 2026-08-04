<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCouponRedemption extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(PlatformCoupon::class, 'platform_coupon_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
