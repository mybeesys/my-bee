<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TaxProfile;
use App\Services\Concerns\ResolvesInvoiceProductLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    use ResolvesInvoiceProductLines;

    public static function instance(): self
    {
        return new self();
    }

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'items.product',
            'items.productVariant',
            'items.taxProfile',
            'items.purchasesReturnsDetails',
            'items.invoice',
            'items.user',
            'additionalCosts.type',
            'additionalCosts.taxProfile',
            'purchasePayments',
            'supplier.acc4',
            'user',
            'reviewedBy',
            'stocks',
            'purchasesReturns',
            'warehouse',
            'paymentVoucher',
        ];
    }

    /**
     * One-shot confirmed purchase invoice matching Filament CreatePurchaseInvoice.
     *
     * @param  array<string, mixed>  $payload
     */
    public function commit(array $payload, int $tenantId, int $userId): Invoice
    {
        return DB::transaction(function () use ($payload, $tenantId, $userId) {
            $items = $payload['items'] ?? [];
            $hasLineDiscount = collect($items)->contains(fn (array $item) => (float) ($item['discount'] ?? 0) > 0);

            $invoice = Invoice::create([
                'tenant_id' => $tenantId,
                'no' => generate_invoice_no(),
                'status' => 'confirmed',
                'type' => Invoice::$TYPE_PURCHASES,
                'payment_method' => 'cash_on_delivery',
                'payment_terms' => $payload['payment_terms'] ?? InvoicePaymentTermsService::TERM_CREDIT,
                'for' => 'supplier',
                'date' => $this->parseDate($payload['date'] ?? null),
                'warehouse_id' => $payload['warehouse_id'],
                'supplier_id' => $payload['supplier_id'],
                'user_id' => $userId,
                'prices_includes_taxes' => (bool) ($payload['prices_includes_taxes'] ?? true),
                'discount_option' => $payload['discount_option'] ?? ($hasLineDiscount ? 'per-item' : 'none'),
                'discount_method' => $payload['discount_method'] ?? ($hasLineDiscount ? 'amount' : 'none'),
                'discount_amount' => $payload['discount_amount'] ?? null,
                'discount_percent' => $payload['discount_percent'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'temp' => false,
            ]);

            foreach ($items as $line) {
                $this->createItem($invoice, $line, $tenantId, $userId);
            }

            foreach ($payload['additional_costs'] ?? [] as $cost) {
                $this->createAdditionalCost($invoice, $cost, $tenantId);
            }

            $invoice->refresh();
            $invoice->load(['items', 'additionalCosts.type', 'additionalCosts.taxProfile']);
            $invoice->confirmPurchaseInvoice();

            $this->recordOptionalCreditPayment($invoice->refresh(), $payload);

            return $invoice->fresh()->load(self::eagerLoads())->loadCount('purchasesReturns');
        });
    }

    /**
     * Credit payment on a confirmed credit invoice (web payments tab, works after lock).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordCreditPayment(Invoice $invoice, array $payload): Invoice
    {
        if ($invoice->type !== Invoice::$TYPE_PURCHASES) {
            throw ValidationException::withMessages([
                'invoice' => __('fields.invoice_locked_statement'),
            ]);
        }

        if ($invoice->status !== 'confirmed' || $invoice->temp) {
            throw ValidationException::withMessages([
                'invoice' => __('fields.invoice_locked_statement'),
            ]);
        }

        if (! InvoicePaymentTermsService::instance()->isCredit($invoice)) {
            throw ValidationException::withMessages([
                'payment_terms' => __('fields.payment_terms_credit'),
            ]);
        }

        $this->recordOptionalCreditPayment($invoice, ['credit_payment' => $payload]);

        return $invoice->fresh()->load(self::eagerLoads())->loadCount('purchasesReturns');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function createItem(Invoice $invoice, array $line, int $tenantId, int $userId): void
    {
        $resolved = $this->resolveProduct($line);
        $qty = (int) $line['qty'];
        $price = (float) ($line['unit_cost'] ?? $line['price'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);
        $taxProfile = TaxProfile::with('taxes')->find($line['tax_profile_id'] ?? null);
        $pricesIncludeTaxes = (bool) $invoice->prices_includes_taxes;
        $tax = 0;

        if ($taxProfile) {
            $subTotal = ($price * $qty) - $discount;
            $tax = MathService::instance()->getTaxFromTaxProfile($subTotal, $taxProfile, $pricesIncludeTaxes);
        }

        $invoice->items()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'product_id' => $resolved['product_id'],
            'product_variant_id' => $resolved['product_variant_id'],
            'name' => $resolved['name'],
            'price' => $price,
            'qty' => $qty,
            'discount' => $discount,
            'tax' => $tax,
            'tax_profile_id' => $taxProfile?->id,
            'tax_profile_data' => $taxProfile?->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $cost
     */
    protected function createAdditionalCost(Invoice $invoice, array $cost, int $tenantId): void
    {
        $taxProfile = TaxProfile::with('taxes')->find($cost['tax_profile_id'] ?? null);
        $amount = (float) $cost['cost'];

        $invoice->additionalCosts()->create([
            'tenant_id' => $tenantId,
            'additional_cost_type_id' => $cost['additional_cost_type_id'],
            'statement' => $cost['statement'],
            'cost' => $amount,
            'tax_profile_id' => $taxProfile?->id,
            'tax_profile_data' => $taxProfile?->toArray(),
        ]);
    }
}
