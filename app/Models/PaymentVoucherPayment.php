<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PaymentVoucherPayment extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $guarded = [];

    public function paymentVoucher(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function model()
    {
        return $this->morphTo();
    }
}
