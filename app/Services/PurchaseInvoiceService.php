<?php

namespace App\Services;

use App\Models\Acc4;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
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
                'discount_option' => $hasLineDiscount ? 'per-item' : 'none',
                'discount_method' => $hasLineDiscount ? 'amount' : 'none',
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

    /**
     * @param  array<string, mixed>  $line
     * @return array{product_id: int, product_variant_id: int|null, name: string}
     */
    public function resolveProduct(array $line): array
    {
        $product = Product::query()->with('variants')->findOrFail($line['product_id']);
        $variantId = $line['product_variant_id'] ?? null;

        if (empty($variantId) && ! empty($line['selected_variant_options_ids'])) {
            $variantId = $this->variantIdFromOptionIds($product, $line['selected_variant_options_ids']);
        }

        if ($product->type === Product::$TYPE_VARIANTS && empty($variantId)) {
            throw ValidationException::withMessages([
                'items' => __('validation.required', ['attribute' => 'product_variant_id']),
            ]);
        }

        if (! empty($variantId)) {
            $variant = $product->variants->firstWhere('id', (int) $variantId)
                ?? ProductVariant::query()->findOrFail($variantId);

            if ((int) $variant->product_id !== (int) $product->id) {
                throw ValidationException::withMessages([
                    'items' => __('validation.exists', ['attribute' => 'product_variant_id']),
                ]);
            }

            return [
                'product_id' => (int) $product->id,
                'product_variant_id' => (int) $variant->id,
                'name' => $variant->name,
            ];
        }

        return [
            'product_id' => (int) $product->id,
            'product_variant_id' => null,
            'name' => $product->name,
        ];
    }

    /**
     * @param  array<int, mixed>  $optionIds
     */
    protected function variantIdFromOptionIds(Product $product, array $optionIds): ?int
    {
        $wanted = array_map('intval', $optionIds);
        sort($wanted);

        foreach ($product->variants as $variant) {
            $have = array_map('intval', $variant->variant_library_options_ids ?? []);
            sort($have);

            if ($have === $wanted) {
                return (int) $variant->id;
            }
        }

        throw ValidationException::withMessages([
            'items' => __('validation.exists', ['attribute' => 'selected_variant_options_ids']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordOptionalCreditPayment(Invoice $invoice, array $payload): void
    {
        $credit = $payload['credit_payment'] ?? null;

        if (! is_array($credit)) {
            return;
        }

        $amount = (float) ($credit['amount'] ?? 0);

        if ($amount <= 0) {
            return;
        }

        InvoicePaymentTermsService::instance()->recordCreditPayment($invoice, [
            'amount' => $amount,
            'account_code' => $credit['account_code'] ?? Acc4::defaultCollectionAccountCode(),
            'date' => $this->parseDate($credit['date'] ?? null),
            'statement' => $credit['statement'] ?? '',
        ]);
    }

    public function parseDate(mixed $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date;
        }

        if (! is_string($date) || trim($date) === '') {
            return now();
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::parse($date);
    }
}
