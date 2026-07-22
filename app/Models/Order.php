<?php

namespace App\Models;

use App\Services\OrderDiscountService;
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
        'coupon_data' => 'array',
    ];

    protected $with = ['invoice'];

    public static string $STATUS_NEW = "new";
    public static string $STATUS_DELIVERY_IN_PROGRESS = "delivery-in-progress";
    public static string $STATUS_PACKAGING = "packaging";
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

    public function scopePackaging(Builder $builder): Builder
    {
        return $builder->where('status', self::$STATUS_PACKAGING);
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

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function coupon(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function getExtrasTotalAttribute()
    {
        $total = 0;
        foreach ($this->invoice->items as $item) {
            $total += $item->extras_total;
        }
        return $total;
    }

    public function getTotalAttribute()
    {
        return OrderDiscountService::instance()->orderGrandTotal($this);
    }

    public function getFullAddressAttribute()
    {
        $address = "";

        if($this->state)
            $address = $this->state->name;

        if($this->city)
            $address = $address . " - " . $this->city->name;

        return $address . " - " . $this->delivery_address;
    }
}
