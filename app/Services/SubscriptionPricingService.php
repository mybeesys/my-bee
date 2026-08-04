<?php

namespace App\Services;

use App\Models\Plan;

class SubscriptionPricingService
{
    public const BILLING_MONTHLY = 'monthly';

    public const BILLING_YEARLY = 'yearly';

    public static function instance(): self
    {
        return new self();
    }

    public function vatPercent(): float
    {
        $fromSetting = setting('subscription_vat_percent', null);

        if (filled($fromSetting) && is_numeric($fromSetting)) {
            return max(0, (float) $fromSetting);
        }

        return max(0, (float) config('subscription.vat_percent', 15));
    }

    public function yearlyPaidMonths(): int
    {
        return max(1, (int) config('subscription.yearly_paid_months', 10));
    }

    public function yearlyDiscountMonths(): int
    {
        return max(0, (int) config('subscription.yearly_discount_months', 2));
    }

    public function normalizeBillingPeriod(?string $billingPeriod): string
    {
        return $billingPeriod === self::BILLING_YEARLY
            ? self::BILLING_YEARLY
            : self::BILLING_MONTHLY;
    }

    /**
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
     *     currency: string
     * }
     */
    public function quote(Plan $plan, ?string $billingPeriod = self::BILLING_MONTHLY): array
    {
        $period = $this->normalizeBillingPeriod($billingPeriod);
        $decimals = currency_decimals();
        $monthly = round((float) $plan->price, $decimals);
        $isFree = $monthly <= 0.0;
        $taxPercent = $this->vatPercent();

        if ($isFree) {
            return [
                'billing_period' => $period,
                'monthly_price' => 0.0,
                'months' => $period === self::BILLING_YEARLY ? 12 : 1,
                'discount_months' => $period === self::BILLING_YEARLY ? $this->yearlyDiscountMonths() : 0,
                'subtotal_ex_tax' => 0.0,
                'tax_percent' => $taxPercent,
                'tax_amount' => 0.0,
                'total_inc_tax' => 0.0,
                'is_free' => true,
                'currency' => main_currency_iso_code(),
            ];
        }

        $months = $period === self::BILLING_YEARLY
            ? $this->yearlyPaidMonths()
            : 1;

        $discountMonths = $period === self::BILLING_YEARLY
            ? $this->yearlyDiscountMonths()
            : 0;

        $subtotal = round($monthly * $months, $decimals);
        $taxAmount = round(
            MathService::instance()->getTax($subtotal, $taxPercent, false),
            $decimals
        );
        $total = round($subtotal + $taxAmount, $decimals);

        return [
            'billing_period' => $period,
            'monthly_price' => $monthly,
            'months' => $months,
            'discount_months' => $discountMonths,
            'subtotal_ex_tax' => $subtotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_inc_tax' => $total,
            'is_free' => false,
            'currency' => main_currency_iso_code(),
        ];
    }

    public function formatMoney(float $amount, ?string $currency = null): string
    {
        $currency ??= main_currency_iso_code();

        return trim($currency . ' ' . format_amount($amount));
    }
}
