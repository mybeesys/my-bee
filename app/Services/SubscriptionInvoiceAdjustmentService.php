<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Validation\ValidationException;

class SubscriptionInvoiceAdjustmentService
{
    public static function instance(): self
    {
        return new self();
    }

    /**
     * Apply a super-admin percentage waiver on the taxable base (after coupon, before VAT).
     * VAT is recalculated on the remaining base so recognized revenue (`price`) drops accordingly.
     *
     * @return array{
     *     percent: float,
     *     base_ex_tax: float,
     *     admin_discount_amount: float,
     *     price_ex_tax: float,
     *     tax_percent: float,
     *     tax_amount: float,
     *     total_inc_tax: float,
     *     waived_inc_tax: float,
     *     original_total_inc_tax: float,
     *     currency: string
     * }
     */
    public function preview(Subscription $subscription, float $percent): array
    {
        $this->assertCanAdjust($subscription);

        $decimals = currency_decimals();
        $percent = $this->normalizePercent($percent);
        $baseExTax = $this->taxableBaseBeforeAdminDiscount($subscription);
        $taxPercent = (float) ($subscription->tax_percent ?? SubscriptionPricingService::instance()->vatPercent());
        $originalTotal = $this->originalTotalIncTax($subscription);

        $adminDiscount = round($baseExTax * ($percent / 100), $decimals);
        $priceExTax = max(0, round($baseExTax - $adminDiscount, $decimals));
        $taxAmount = round(MathService::instance()->getTax($priceExTax, $taxPercent, false), $decimals);
        $total = round($priceExTax + $taxAmount, $decimals);

        return [
            'percent' => $percent,
            'base_ex_tax' => $baseExTax,
            'admin_discount_amount' => $adminDiscount,
            'price_ex_tax' => $priceExTax,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_inc_tax' => $total,
            'waived_inc_tax' => max(0, round($originalTotal - $total, $decimals)),
            'original_total_inc_tax' => $originalTotal,
            'currency' => main_currency_iso_code(),
        ];
    }

    public function apply(Subscription $subscription, float $percent, ?string $note = null, ?int $userId = null): Subscription
    {
        $quote = $this->preview($subscription, $percent);

        if ($subscription->original_price_ex_tax === null) {
            $subscription->original_price_ex_tax = $this->taxableBaseBeforeAdminDiscount($subscription);
            $subscription->original_tax_amount = (float) ($subscription->tax_amount ?? 0);
            $subscription->original_price = (float) ($subscription->price ?? 0);
        }

        $subscription->fill([
            'price_ex_tax' => $quote['price_ex_tax'],
            'tax_amount' => $quote['tax_amount'],
            'price' => $quote['total_inc_tax'],
            'admin_discount_percent' => $quote['percent'],
            'admin_discount_amount' => $quote['admin_discount_amount'],
            'admin_discount_note' => filled($note) ? trim($note) : null,
            'admin_discounted_at' => now(),
            'admin_discounted_by' => $userId,
        ])->save();

        return $subscription->refresh();
    }

    public function restore(Subscription $subscription): Subscription
    {
        if ($subscription->original_price_ex_tax === null && (float) ($subscription->admin_discount_percent ?? 0) <= 0) {
            return $subscription;
        }

        $subscription->fill([
            'price_ex_tax' => $subscription->original_price_ex_tax ?? $subscription->price_ex_tax,
            'tax_amount' => $subscription->original_tax_amount ?? $subscription->tax_amount,
            'price' => $subscription->original_price ?? $subscription->price,
            'admin_discount_percent' => null,
            'admin_discount_amount' => null,
            'admin_discount_note' => null,
            'admin_discounted_at' => null,
            'admin_discounted_by' => null,
            'original_price_ex_tax' => null,
            'original_tax_amount' => null,
            'original_price' => null,
        ])->save();

        return $subscription->refresh();
    }

    public function taxableBaseBeforeAdminDiscount(Subscription $subscription): float
    {
        $decimals = currency_decimals();

        if ($subscription->original_price_ex_tax !== null) {
            return round((float) $subscription->original_price_ex_tax, $decimals);
        }

        return round((float) ($subscription->price_ex_tax ?? $subscription->price ?? 0), $decimals);
    }

    public function originalTotalIncTax(Subscription $subscription): float
    {
        $decimals = currency_decimals();

        if ($subscription->original_price !== null) {
            return round((float) $subscription->original_price, $decimals);
        }

        return round((float) ($subscription->price ?? 0), $decimals);
    }

    public function waivedIncTax(Subscription $subscription): float
    {
        if ((float) ($subscription->admin_discount_percent ?? 0) <= 0) {
            return 0.0;
        }

        return max(0, round(
            $this->originalTotalIncTax($subscription) - (float) ($subscription->price ?? 0),
            currency_decimals()
        ));
    }

    protected function assertCanAdjust(Subscription $subscription): void
    {
        $hasBilledAmount = $this->originalTotalIncTax($subscription) > 0
            || $this->taxableBaseBeforeAdminDiscount($subscription) > 0;

        if (! $hasBilledAmount) {
            throw ValidationException::withMessages([
                'percent' => __('fields.revenue_admin_discount_free_plan'),
            ]);
        }
    }

    protected function normalizePercent(float $percent): float
    {
        if ($percent <= 0 || $percent > 100) {
            throw ValidationException::withMessages([
                'percent' => __('fields.revenue_admin_discount_percent_invalid'),
            ]);
        }

        return round($percent, 2);
    }
}
