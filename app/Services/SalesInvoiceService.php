<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PriceOffer;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use App\Services\Concerns\ResolvesInvoiceProductLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
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
            'items.product.extras.extra',
            'items.productVariant',
            'items.taxProfile',
            'items.extras.productExtra.extra',
            'items.salesReturnsDetails',
            'items.invoice',
            'items.user',
            'additionalCosts.type',
            'additionalCosts.taxProfile',
            'services.type',
            'services.taxProfile',
            'salesPayments',
            'customer.acc4',
            'user',
            'reviewedBy',
            'stocks',
            'salesReturns',
            'receiptVoucher',
            'order',
        ];
    }

    /**
     * One-shot confirmed sales invoice matching Filament CreateSalesInvoice.
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
                'type' => Invoice::$TYPE_SALES,
                'payment_method' => 'cash_on_delivery',
                'payment_terms' => $payload['payment_terms'] ?? InvoicePaymentTermsService::TERM_CREDIT,
                'for' => 'customer',
                'date' => $this->parseDate($payload['date'] ?? null),
                'customer_id' => $payload['customer_id'],
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

            foreach ($payload['services'] ?? [] as $service) {
                $this->createService($invoice, $service, $tenantId);
            }

            foreach ($payload['additional_costs'] ?? [] as $cost) {
                $this->createAdditionalCost($invoice, $cost, $tenantId);
            }

            $invoice->refresh();
            $invoice->load(['items.extras', 'additionalCosts', 'services']);
            $invoice->confirmSalesInvoice();

            $this->recordOptionalCreditPayment($invoice->refresh(), $payload);

            return $invoice->fresh()->load(self::eagerLoads())->loadCount('salesReturns');
        });
    }

    /**
     * Credit payment on a confirmed credit invoice (web payments tab, works after lock).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordCreditPayment(Invoice $invoice, array $payload): Invoice
    {
        if ($invoice->type !== Invoice::$TYPE_SALES) {
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

        return $invoice->fresh()->load(self::eagerLoads())->loadCount('salesReturns');
    }

    /**
     * Prefill for converting a price offer — does not create an invoice.
     *
     * @return array<string, mixed>
     */
    public function priceOfferPrefill(PriceOffer $offer): array
    {
        if ($offer->isExpired()) {
            throw ValidationException::withMessages([
                'price_offer_id' => __('fields.price_offer_expired_cannot_convert'),
            ]);
        }

        $offer->loadMissing([
            'customer',
            'details.item',
            'details.offerDetailsExtras',
            'details.taxProfile',
            'services.type',
            'services.taxProfile',
            'additionalCosts.type',
            'additionalCosts.taxProfile',
        ]);

        $items = [];

        foreach ($offer->details as $detail) {
            $item = $detail->item;
            $isVariant = $item instanceof ProductVariant;
            $productId = $isVariant ? $item->product_id : $item?->id;
            $variantId = $isVariant ? $item->id : null;

            if (! $productId) {
                continue;
            }

            $items[] = [
                'productId' => $productId,
                'productVariantId' => $variantId,
                'name' => $item->name,
                'qty' => (int) $detail->qty,
                'price' => number_format((float) $detail->unit_price, currency_decimals(), '.', ''),
                'discount' => number_format((float) $detail->discount, currency_decimals(), '.', ''),
                'taxProfileId' => $detail->tax_profile_id,
                'extras' => $detail->offerDetailsExtras->pluck('product_extra_id')->filter()->values()->all(),
            ];
        }

        $services = [];
        foreach ($offer->services as $service) {
            $services[] = [
                'serviceTypeId' => $service->service_type_id,
                'price' => number_format((float) $service->price, currency_decimals(), '.', ''),
                'description' => $service->description,
                'taxProfileId' => $service->tax_profile_id,
            ];
        }

        $additionalCosts = [];
        foreach ($offer->additionalCosts as $cost) {
            $additionalCosts[] = [
                'additionalCostTypeId' => $cost->additional_cost_type_id,
                'cost' => number_format((float) $cost->cost, currency_decimals(), '.', ''),
                'statement' => $cost->statement,
                'taxProfileId' => $cost->tax_profile_id,
            ];
        }

        return [
            'priceOfferId' => $offer->id,
            'priceOfferNo' => $offer->no,
            'description' => $offer->description,
            'expired' => false,
            'customerId' => $offer->customer_id,
            'customerName' => $offer->customer?->name,
            'pricesIncludesTaxes' => (bool) $offer->prices_includes_taxes,
            'discountOption' => $offer->discount_option,
            'discountMethod' => $offer->discount_method,
            'discountAmount' => $offer->discount_amount,
            'discountPercent' => $offer->discount_percent,
            'items' => $items,
            'services' => $services,
            'additionalCosts' => $additionalCosts,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function createItem(Invoice $invoice, array $line, int $tenantId, int $userId): void
    {
        $resolved = $this->resolveProduct($line);
        $qty = (int) $line['qty'];
        $price = (float) ($line['price'] ?? $line['unit_cost'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);
        $extraIds = $line['extras'] ?? $line['product_extras_ids'] ?? [];
        $extraIds = is_array($extraIds) ? array_values(array_filter($extraIds)) : [];
        $taxProfile = TaxProfile::with('taxes')->find($line['tax_profile_id'] ?? null);
        $pricesIncludeTaxes = (bool) $invoice->prices_includes_taxes;

        $extrasTotal = 0;
        if ($extraIds !== []) {
            $extrasTotal = (float) PricingService::instance()->getRetailItemsPrices(
                ProductExtra::with('lastPrice')->findMany($extraIds)
            ) * $qty;
        }

        $tax = 0;
        if ($taxProfile) {
            $subTotal = ($price * $qty) + $extrasTotal - $discount;
            $tax = MathService::instance()->getTaxFromTaxProfile($subTotal, $taxProfile, $pricesIncludeTaxes);
        }

        $invoiceItem = $invoice->items()->create([
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

        foreach ($extraIds as $productExtraId) {
            $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($productExtraId);

            if ((int) $productExtra->product_id !== (int) $resolved['product_id']) {
                throw ValidationException::withMessages([
                    'items' => __('validation.exists', ['attribute' => 'extras']),
                ]);
            }

            $invoiceItem->extras()->create([
                'tenant_id' => $tenantId,
                'invoice_item_id' => $invoiceItem->id,
                'product_extra_id' => $productExtra->id,
                'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                'display_name' => $productExtra->name,
                'qty' => 1,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $service
     */
    protected function createService(Invoice $invoice, array $service, int $tenantId): void
    {
        $taxProfile = TaxProfile::with('taxes')->find($service['tax_profile_id'] ?? null);

        $invoice->services()->create([
            'tenant_id' => $tenantId,
            'service_type_id' => $service['service_type_id'],
            'item_id' => $invoice->id,
            'item_type' => Invoice::class,
            'price' => $service['price'],
            'description' => $service['description'],
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

        $invoice->additionalCosts()->create([
            'tenant_id' => $tenantId,
            'additional_cost_type_id' => $cost['additional_cost_type_id'],
            'statement' => $cost['statement'],
            'cost' => $cost['cost'],
            'tax_profile_id' => $taxProfile?->id,
            'tax_profile_data' => $taxProfile?->toArray(),
        ]);
    }
}
