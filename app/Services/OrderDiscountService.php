<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\Product;

class OrderDiscountService
{
    public static function instance(): self
    {
        return new self();
    }

    public function orderHasCouponDiscount(Order $order): bool
    {
        return filled($order->coupon_id)
            || filled($order->coupon_data)
            || (float) $order->discount > 0;
    }

    public function resolveCoupon(Order $order): ?Coupon
    {
        if ($order->coupon_id) {
            return Coupon::query()->find($order->coupon_id);
        }

        $code = $order->coupon_data['code'] ?? null;

        return filled($code) ? Coupon::query()->firstWhere('code', $code) : null;
    }

    public function invoiceItemsSubtotalBeforeDiscount(Invoice $invoice): float
    {
        $invoice->loadMissing(['items']);

        $total = 0.0;

        foreach ($invoice->items as $item) {
            $total += ((float) $item->price * (float) $item->qty) + (float) $item->extras_total;
        }

        return $total;
    }

    public function resolveCouponDiscountAmount(Order $order, ?Invoice $invoice = null): float
    {
        if ((float) $order->discount > 0) {
            return (float) $order->discount;
        }

        $coupon = $this->resolveCoupon($order);

        if (! $coupon) {
            return 0.0;
        }

        $invoice ??= $order->invoice;

        if (! $invoice) {
            return 0.0;
        }

        return (float) CouponService::instance()->amount(
            $coupon->code,
            $this->invoiceItemsSubtotalBeforeDiscount($invoice)
        );
    }

    /** @return array{discount_option: string, discount_method: ?string, discount_amount: ?float, discount_percent: ?float} */
    public function invoiceDiscountAttributes(Order $order, ?Invoice $invoice = null): array
    {
        if (! $this->orderHasCouponDiscount($order)) {
            return [
                'discount_option' => 'none',
                'discount_method' => null,
                'discount_amount' => null,
                'discount_percent' => null,
            ];
        }

        $coupon = $this->resolveCoupon($order);

        if ($coupon && $coupon->type === 'percent') {
            return [
                'discount_option' => 'overall',
                'discount_method' => 'percent',
                'discount_amount' => null,
                'discount_percent' => (float) $coupon->value,
            ];
        }

        $invoice ??= $order->invoice;

        return [
            'discount_option' => 'overall',
            'discount_method' => 'amount',
            'discount_amount' => $invoice
                ? $this->resolveCouponDiscountAmount($order, $invoice)
                : (float) $order->discount,
            'discount_percent' => null,
        ];
    }

    public function resolveOverallDiscountAmount(Invoice $invoice): float
    {
        if ($invoice->discount_option !== 'overall') {
            return 0.0;
        }

        return match ($invoice->discount_method) {
            'amount' => (float) ($invoice->discount_amount ?? 0),
            'percent' => $this->invoiceItemsSubtotalBeforeDiscount($invoice) * ((float) ($invoice->discount_percent ?? 0) / 100),
            default => 0.0,
        };
    }

    public function orderDiscountAmount(Order $order): float
    {
        if (! $this->orderHasCouponDiscount($order)) {
            return 0.0;
        }

        return $this->resolveCouponDiscountAmount($order);
    }

    public function orderGrandTotal(Order $order): float
    {
        $invoice = $order->invoice;

        if (! $invoice) {
            return 0.0;
        }

        $total = (float) $invoice->getItemsCost(true, true, true);

        if ($invoice->discount_option === 'overall') {
            $total -= $this->resolveOverallDiscountAmount($invoice);
        }

        return max(0, $total);
    }

    public function syncInvoiceDiscountFromOrder(Order $order): void
    {
        $invoice = $order->invoice;

        if (! $invoice) {
            return;
        }

        $invoice->loadMissing(['items.extras', 'items.product.taxProfile', 'items.productVariant.product.taxProfile']);

        if ($this->orderHasCouponDiscount($order)) {
            $invoice->update($this->invoiceDiscountAttributes($order, $invoice));

            $invoice->refresh();

            $subtotal = $this->invoiceItemsSubtotalBeforeDiscount($invoice);
            $discount = $this->resolveOverallDiscountAmount($invoice);
            $ratio = ($subtotal > 0 && $discount > 0)
                ? max(0, ($subtotal - $discount) / $subtotal)
                : 1.0;

            foreach ($invoice->items as $item) {
                $item->update([
                    'discount' => 0,
                    'tax' => round($this->recalculateInvoiceItemTax($invoice, $item) * $ratio, 4),
                ]);
            }

            $order->update([
                'discount' => $this->resolveCouponDiscountAmount($order, $invoice),
            ]);

            return;
        }

        foreach ($invoice->items as $item) {
            if ((float) $item->discount !== 0.0) {
                continue;
            }

            $item->update([
                'tax' => $this->recalculateInvoiceItemTax($invoice, $item),
            ]);
        }
    }

    public function recalculateInvoiceItemTax(Invoice $invoice, InvoiceItem $item): float
    {
        $item->loadMissing(['product.taxProfile', 'productVariant.product.taxProfile']);

        $subTotal = ((float) $item->price * (float) $item->qty) + (float) $item->extras_total;
        $pricesIncludeTaxes = (bool) ($invoice->prices_includes_taxes ?? true);

        if ($item->tax_profile_data) {
            $percent = collect($item->tax_profile_data['taxes'] ?? [])->sum('percent');

            if ($percent > 0) {
                return (float) MathService::instance()->getTax($subTotal, $percent, $pricesIncludeTaxes);
            }
        }

        $product = $item->productVariant?->product ?? $item->product;

        if ($product instanceof Product && $product->taxProfile) {
            return (float) MathService::instance()->getTaxFromTaxProfile(
                $subTotal,
                $product->taxProfile,
                $pricesIncludeTaxes
            );
        }

        return (float) $item->tax;
    }
}
