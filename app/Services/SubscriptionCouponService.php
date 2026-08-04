<?php

namespace App\Services;

use App\Models\Client;
use App\Models\PlatformCoupon;
use App\Models\PlatformCouponRedemption;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SubscriptionCouponService
{
    public static function instance(): self
    {
        return new self();
    }

    public function hasActiveCoupons(): bool
    {
        return PlatformCoupon::query()->valid()->exists();
    }

    public function findUsable(string $code, Client $client): PlatformCoupon
    {
        $normalized = Str::upper(trim($code));

        if ($normalized === '') {
            throw new InvalidArgumentException(__('fields.subscription_coupon_required'));
        }

        /** @var PlatformCoupon|null $coupon */
        $coupon = PlatformCoupon::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->first();

        if (! $coupon) {
            throw new InvalidArgumentException(__('fields.subscription_coupon_invalid'));
        }

        if (! $coupon->active) {
            throw new InvalidArgumentException(__('fields.subscription_coupon_inactive'));
        }

        if ($coupon->isExpired()) {
            throw new InvalidArgumentException(__('fields.subscription_coupon_expired'));
        }

        $alreadyUsed = PlatformCouponRedemption::query()
            ->where('platform_coupon_id', $coupon->id)
            ->where('client_id', $client->id)
            ->exists();

        if ($alreadyUsed) {
            throw new InvalidArgumentException(__('fields.subscription_coupon_already_used'));
        }

        return $coupon;
    }

    /**
     * @param  array{
     *     billing_period: string,
     *     monthly_price: float,
     *     months: int,
     *     discount_months: int,
     *     subtotal_ex_tax: float,
     *     tax_percent: float,
     *     tax_amount: float,
     *     total_inc_tax: float,
     *     is_free: bool,
     *     currency: string
     * }  $quote
     * @return array{
     *     billing_period: string,
     *     monthly_price: float,
     *     months: int,
     *     discount_months: int,
     *     subtotal_ex_tax: float,
     *     tax_percent: float,
     *     tax_amount: float,
     *     total_inc_tax: float,
     *     is_free: bool,
     *     currency: string,
     *     discount_amount: float,
     *     subtotal_before_discount: float,
     *     coupon_code: string|null,
     *     coupon_id: int|null
     * }
     */
    public function applyToQuote(array $quote, PlatformCoupon $coupon): array
    {
        $decimals = currency_decimals();
        $before = round((float) $quote['subtotal_ex_tax'], $decimals);
        $discount = $this->calculateDiscount($before, $coupon, $decimals);
        $subtotal = max(0, round($before - $discount, $decimals));
        $taxPercent = (float) $quote['tax_percent'];
        $taxAmount = round(
            MathService::instance()->getTax($subtotal, $taxPercent, false),
            $decimals
        );
        $total = round($subtotal + $taxAmount, $decimals);

        return array_merge($quote, [
            'subtotal_before_discount' => $before,
            'discount_amount' => $discount,
            'subtotal_ex_tax' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_inc_tax' => $total,
            'is_free' => $total <= 0.0,
            'coupon_code' => $coupon->code,
            'coupon_id' => $coupon->id,
        ]);
    }

    public function calculateDiscount(float $subtotalExTax, PlatformCoupon $coupon, ?int $decimals = null): float
    {
        $decimals ??= currency_decimals();
        $value = max(0, (float) $coupon->value);

        if ($coupon->isPercent()) {
            $percent = min(100, $value);

            return round($subtotalExTax * ($percent / 100), $decimals);
        }

        return round(min($subtotalExTax, $value), $decimals);
    }
}
