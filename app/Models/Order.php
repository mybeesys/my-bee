<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends BaseModel
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'canceled_date' => 'datetime',
        'delivery_date' => 'datetime',
        'paid_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static string $STATUS_PENDING = "pending";
    public static string $STATUS_DELIVERED = "delivered";
    public static string $STATUS_CANCELLED = "cancelled";

    public static string $PAYMENT_METHOD_CASH = "cash";
    public static string $PAYMENT_METHOD_MBOK = "mbok";
    public static string $PAYMENT_METHOD_FAWRY = "fawry";
    public static string $PAYMENT_METHOD_OTHER = "other";

    public function scopePending(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_PENDING);
    }

    public function scopeDelivered(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_DELIVERED);
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getSubTotalAttribute()
    {
        $price = 0;
        foreach ($this->details as $detail)
        {
            $price += $detail->unit_price_sdg * $detail->qty;
        }
        return $price;
    }

    public function getTotalAttribute()
    {
        return $this->sub_total + $this->delivery + $this->delivery_extra - $this->discount;
    }
}
