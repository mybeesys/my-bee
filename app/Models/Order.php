<?php

namespace App\Models;

use App\Traits\HasPrefixedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Order extends BaseModel implements HasMedia
{
    use HasFactory, HasPrefixedId, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'canceled_date' => 'datetime',
        'delivery_date' => 'datetime',
        'paid_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static string $STATUS_NEW = "new";
    public static string $STATUS_DELIVERY_IN_PROGRESS = "delivery-in-progress";
    public static string $STATUS_READY = "ready";
    public static string $STATUS_COMPLETED = "completed";
    public static string $STATUS_CANCELLED = "cancelled";

    public static string $PAYMENT_METHOD_CASH = "cash";
    public static string $PAYMENT_METHOD_MBOK = "mbok";
    public static string $PAYMENT_METHOD_FAWRY = "fawry";
    public static string $PAYMENT_METHOD_OTHER = "other";

    public function scopeNew(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_NEW);
    }

    public function scopeDeliveryInProgress(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_DELIVERY_IN_PROGRESS);
    }

    public function scopeCompleted(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_COMPLETED);
    }

    public function scopeCancelled(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_CANCELLED);
    }

    public function scopeDiscount(Builder $builder): Builder
    {
        return $builder->where('discount', '>', 0);
    }

    public function scopeCash(Builder $builder): Builder
    {
        return $builder->where('payment_method', self::$PAYMENT_METHOD_CASH);
    }

    public function scopeMbok(Builder $builder): Builder
    {
        return $builder->where('payment_method', self::$PAYMENT_METHOD_MBOK);
    }

    public function scopeFawry(Builder $builder): Builder
    {
        return $builder->where('payment_method', self::$PAYMENT_METHOD_FAWRY);
    }

    public function scopeOtherPaymentMethod(Builder $builder): Builder
    {
        return $builder->where('payment_method', self::$PAYMENT_METHOD_OTHER);
    }


    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSubTotalAttribute()
    {
        $price = 0;
        foreach ($this->details as $detail) {
            if (!$detail->cancelled)
                $price += $detail->unit_price * $detail->qty;
        }
        return $price;
    }

    public function getTotalAttribute()
    {
        return $this->sub_total + $this->delivery + $this->delivery_extra - $this->discount;
    }
}
