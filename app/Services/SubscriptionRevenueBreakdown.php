<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\PlatformCoupon;

class SubscriptionRevenueBreakdown
{
    public static function instance(): self
    {
        return new self();
    }

    /**
     * @return array{
     *     client_name: string|null,
     *     plan_name: string|null,
     *     billing_period_label: string,
     *     monthly_price: float,
     *     months: int,
     *     discount_months: int,
     *     subtotal_before_discount: float,
     *     discount_amount: float,
     *     admin_discount_percent: float,
     *     admin_discount_amount: float,
     *     subtotal_ex_tax: float,
     *     tax_percent: float,
     *     tax_amount: float,
     *     total_inc_tax: float,
     *     coupon_code: string|null,
     *     currency: string,
     *     is_free: bool,
     *     steps: array<int, array{label: string, value: string, hint: string|null}>,
     *     formula_summary: string,
     * }
     */
    public function forSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing(['client', 'plan', 'platformCoupon']);

        $pricing = SubscriptionPricingService::instance();
        $period = $pricing->normalizeBillingPeriod($subscription->billing_period);
        $plan = $subscription->plan;
        $monthly = round((float) ($plan?->price ?? 0), currency_decimals());
        $months = $period === SubscriptionPricingService::BILLING_YEARLY
            ? $pricing->yearlyPaidMonths()
            : 1;
        $discountMonths = $period === SubscriptionPricingService::BILLING_YEARLY
            ? $pricing->yearlyDiscountMonths()
            : 0;

        $subtotalExTax = (float) ($subscription->price_ex_tax ?? $subscription->price ?? 0);
        $discountAmount = (float) ($subscription->discount_amount ?? 0);
        $adminDiscountAmount = (float) ($subscription->admin_discount_amount ?? 0);
        $adminDiscountPercent = (float) ($subscription->admin_discount_percent ?? 0);
        $subtotalBeforeDiscount = $discountAmount > 0
            ? round($subtotalExTax + $discountAmount + $adminDiscountAmount, currency_decimals())
            : round($subtotalExTax + $adminDiscountAmount, currency_decimals());

        if ($subscription->price_ex_tax === null && $plan) {
            $quote = $pricing->quote($plan, $period);
            $subtotalBeforeDiscount = (float) $quote['subtotal_ex_tax'];
            if ($subscription->platformCoupon) {
                $quote = SubscriptionCouponService::instance()->applyToQuote($quote, $subscription->platformCoupon);
                $subtotalBeforeDiscount = (float) ($quote['subtotal_before_discount'] ?? $subtotalBeforeDiscount);
                $discountAmount = (float) ($quote['discount_amount'] ?? $discountAmount);
                $subtotalExTax = (float) $quote['subtotal_ex_tax'];
            } else {
                $subtotalExTax = $subtotalBeforeDiscount;
            }
        }

        $taxPercent = (float) ($subscription->tax_percent ?? $pricing->vatPercent());
        $taxAmount = (float) ($subscription->tax_amount ?? 0);
        $totalIncTax = (float) ($subscription->price ?? 0);
        $currency = main_currency_iso_code();

        $billingLabel = $period === SubscriptionPricingService::BILLING_YEARLY
            ? __('fields.yearly')
            : __('fields.monthly');

        $steps = $this->buildSteps(
            $monthly,
            $months,
            $discountMonths,
            $period,
            $subtotalBeforeDiscount,
            $discountAmount,
            $subscription->coupon_code,
            $adminDiscountPercent,
            $adminDiscountAmount,
            $subtotalExTax,
            $taxPercent,
            $taxAmount,
            $totalIncTax,
            $currency,
        );

        return [
            'client_name' => $subscription->client?->name,
            'plan_name' => $plan?->name,
            'billing_period_label' => $billingLabel,
            'monthly_price' => $monthly,
            'months' => $months,
            'discount_months' => $discountMonths,
            'subtotal_before_discount' => $subtotalBeforeDiscount,
            'discount_amount' => $discountAmount,
            'admin_discount_percent' => $adminDiscountPercent,
            'admin_discount_amount' => $adminDiscountAmount,
            'subtotal_ex_tax' => $subtotalExTax,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_inc_tax' => $totalIncTax,
            'coupon_code' => $subscription->coupon_code,
            'currency' => $currency,
            'is_free' => $totalIncTax <= 0.0,
            'steps' => $steps,
            'formula_summary' => $this->formulaSummary($steps, $totalIncTax, $currency),
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, hint: string|null}>
     */
    protected function buildSteps(
        float $monthly,
        int $months,
        int $discountMonths,
        string $period,
        float $subtotalBeforeDiscount,
        float $discountAmount,
        ?string $couponCode,
        float $adminDiscountPercent,
        float $adminDiscountAmount,
        float $subtotalExTax,
        float $taxPercent,
        float $taxAmount,
        float $totalIncTax,
        string $currency,
    ): array {
        $fmt = fn (float $amount): string => trim($currency . ' ' . format_amount($amount));

        $steps = [
            [
                'label' => __('fields.revenue_step_monthly_price'),
                'value' => $fmt($monthly),
                'hint' => null,
            ],
            [
                'label' => __('fields.revenue_step_billing_period'),
                'value' => $period === SubscriptionPricingService::BILLING_YEARLY
                    ? __('fields.revenue_step_yearly_detail', [
                        'months' => $months,
                        'free' => $discountMonths,
                    ])
                    : __('fields.monthly'),
                'hint' => null,
            ],
            [
                'label' => __('fields.revenue_step_subtotal'),
                'value' => $fmt($subtotalBeforeDiscount),
                'hint' => $months > 1
                    ? __('fields.revenue_step_subtotal_hint', [
                        'monthly' => format_amount($monthly),
                        'months' => $months,
                    ])
                    : null,
            ],
        ];

        if ($discountAmount > 0) {
            $steps[] = [
                'label' => __('fields.revenue_step_coupon'),
                'value' => '- ' . $fmt($discountAmount),
                'hint' => filled($couponCode)
                    ? __('fields.revenue_step_coupon_hint', ['code' => $couponCode])
                    : null,
            ];
        }

        if ($adminDiscountAmount > 0) {
            $steps[] = [
                'label' => __('fields.revenue_step_admin_discount', [
                    'percent' => format_amount($adminDiscountPercent),
                ]),
                'value' => '- ' . $fmt($adminDiscountAmount),
                'hint' => __('fields.revenue_step_admin_discount_hint'),
            ];
        }

        if ($discountAmount > 0 || $adminDiscountAmount > 0) {
            $steps[] = [
                'label' => __('fields.revenue_step_after_discount'),
                'value' => $fmt($subtotalExTax),
                'hint' => null,
            ];
        }

        $steps[] = [
            'label' => __('fields.revenue_step_vat', ['percent' => format_amount($taxPercent)]),
            'value' => $fmt($taxAmount),
            'hint' => __('fields.revenue_step_vat_hint', [
                'base' => format_amount($subtotalExTax),
                'percent' => format_amount($taxPercent),
            ]),
        ];

        $steps[] = [
            'label' => __('fields.revenue_step_total'),
            'value' => $fmt($totalIncTax),
            'hint' => __('fields.revenue_step_total_hint'),
        ];

        return $steps;
    }

    /**
     * @param  array<int, array{label: string, value: string, hint: string|null}>  $steps
     */
    protected function formulaSummary(array $steps, float $total, string $currency): string
    {
        return trim($currency . ' ' . format_amount($total));
    }
}
