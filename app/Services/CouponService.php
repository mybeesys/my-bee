<?php


namespace App\Services;


use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;

class CouponService
{

    public static function instance(): self
    {
        return new self();
    }

    public function isValid($coupon): bool
    {
        $coupon = Coupon::firstWhere('code', $coupon);

        if ($coupon and $coupon->valid_until === null)
            return $coupon and $coupon->active; //unlimited time

        return $coupon and $coupon->active and $coupon->valid_until->isFuture();
    }

    public function amount($coupon, $subTotal)
    {
        $coupon = Coupon::firstWhere('code', $coupon);

        if ($coupon and $coupon->type == "fixed")
            return $coupon->value;

        if ($coupon and $coupon->type == "percent" and $subTotal > 0) //discountInAmount
            return $subTotal * ($coupon->value / 100);

        return 0.0;
    }

    public function isUsedByCustomer(Coupon $coupon, $customer_id): bool
    {
        $coupon->loadMissing('usages');
        return $this->usages->where('coupon_id', $coupon->id)->where('customer_id', $customer_id)->first() != null;
    }

    //orders
    public function usages(Coupon $coupon): Collection
    {
        $coupon->loadMissing('usages');
        return $coupon->usages;
    }
}
