<?php

namespace App\Services;

use App\Models\PriceOffer;
use App\Models\PriceOfferDetails;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Models\TaxProfile;
use App\Services\Concerns\ResolvesInvoiceProductLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceOfferService
{
    use ResolvesInvoiceProductLines;

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'customer.acc4',
            'customer.state',
            'customer.city.state',
            'customer.area',
            'details.item',
            'details.offerDetailsExtras.productExtra',
            'details.taxProfile',
            'services.type',
            'services.taxProfile',
            'additionalCosts.type',
            'additionalCosts.taxProfile',
            'tenant',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, int $tenantId, int $userId): PriceOffer
    {
        return DB::transaction(function () use ($payload, $tenantId, $userId) {
            $details = $payload['details'] ?? [];
            $hasLineDiscount = collect($details)->contains(fn (array $line) => (float) ($line['discount'] ?? 0) > 0);

            $offer = PriceOffer::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'customer_id' => $payload['customer_id'],
                'description' => $payload['description'],
                'expires_at' => $payload['expires_at'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'prices_includes_taxes' => (bool) ($payload['prices_includes_taxes'] ?? true),
                'discount_option' => $payload['discount_option'] ?? ($hasLineDiscount ? 'per-item' : 'none'),
                'discount_method' => $payload['discount_method'] ?? ($hasLineDiscount ? 'amount' : 'none'),
                'discount_amount' => $payload['discount_amount'] ?? null,
                'discount_percent' => $payload['discount_percent'] ?? null,
            ]);

            $this->syncDetails($offer, $details, $tenantId, $userId);
            $this->syncServices($offer, $payload['services'] ?? [], $tenantId);
            $this->syncAdditionalCosts($offer, $payload['additional_costs'] ?? [], $tenantId);

            return $offer->fresh()->load(self::eagerLoads())->loadCount('details');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(PriceOffer $offer, array $payload, int $tenantId, int $userId): PriceOffer
    {
        return DB::transaction(function () use ($offer, $payload, $tenantId, $userId) {
            $header = [];

            foreach (['customer_id', 'description', 'notes', 'discount_option', 'discount_method', 'discount_amount', 'discount_percent'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $header[$field] = $payload[$field];
                }
            }

            if (array_key_exists('expires_at', $payload)) {
                $header['expires_at'] = $payload['expires_at'];
            }

            if (array_key_exists('prices_includes_taxes', $payload)) {
                $header['prices_includes_taxes'] = (bool) $payload['prices_includes_taxes'];
            }

            if ($header !== []) {
                $offer->update($header);
            }

            if (array_key_exists('details', $payload)) {
                $this->deleteDetails($offer);
                $this->syncDetails($offer, $payload['details'], $tenantId, $userId);
            }

            if (array_key_exists('services', $payload)) {
                $offer->services()->delete();
                $this->syncServices($offer, $payload['services'], $tenantId);
            }

            if (array_key_exists('additional_costs', $payload)) {
                $offer->additionalCosts()->delete();
                $this->syncAdditionalCosts($offer, $payload['additional_costs'], $tenantId);
            }

            return $offer->fresh()->load(self::eagerLoads())->loadCount('details');
        });
    }

    public function delete(PriceOffer $offer): void
    {
        DB::transaction(function () use ($offer) {
            $this->deleteDetails($offer);
            $offer->services()->delete();
            $offer->additionalCosts()->delete();
            $offer->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    protected function syncDetails(PriceOffer $offer, array $details, int $tenantId, int $userId): void
    {
        foreach ($details as $line) {
            $this->createDetail($offer, $line, $tenantId, $userId);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function createDetail(PriceOffer $offer, array $line, int $tenantId, int $userId): void
    {
        $resolved = $this->resolveProduct($line, 'details');
        $morph = $this->morphItem($resolved);
        $qty = (int) $line['qty'];
        $price = (float) ($line['price'] ?? 0);
        $discount = (float) ($line['discount'] ?? 0);
        $extraIds = $line['extras'] ?? [];
        $extraIds = is_array($extraIds) ? array_values(array_filter($extraIds)) : [];
        $taxProfile = TaxProfile::with('taxes')->find($line['tax_profile_id'] ?? null);
        $pricesIncludeTaxes = (bool) $offer->prices_includes_taxes;

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

        $detail = PriceOfferDetails::create([
            'tenant_id' => $tenantId,
            'price_offer_id' => $offer->id,
            'user_id' => $userId,
            'item_id' => $morph['item_id'],
            'item_type' => $morph['item_type'],
            'unit_price' => $price,
            'discount' => $discount,
            'qty' => $qty,
            'tax' => $tax,
            'tax_profile_id' => $taxProfile?->id,
            'tax_profile_data' => $taxProfile?->toArray(),
        ]);

        foreach ($extraIds as $productExtraId) {
            $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($productExtraId);

            if ((int) $productExtra->product_id !== (int) $resolved['product_id']) {
                throw ValidationException::withMessages([
                    'details' => __('validation.exists', ['attribute' => 'extras']),
                ]);
            }

            $detail->offerDetailsExtras()->create([
                'tenant_id' => $tenantId,
                'price_offer_details_id' => $detail->id,
                'product_extra_id' => $productExtra->id,
                'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                'display_name' => $productExtra->name,
                'qty' => 1,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    protected function syncServices(PriceOffer $offer, array $services, int $tenantId): void
    {
        foreach ($services as $service) {
            $taxProfile = TaxProfile::with('taxes')->find($service['tax_profile_id'] ?? null);

            $offer->services()->create([
                'tenant_id' => $tenantId,
                'service_type_id' => $service['service_type_id'],
                'price' => $service['price'],
                'description' => $service['description'],
                'tax_profile_id' => $taxProfile?->id,
                'tax_profile_data' => $taxProfile?->toArray(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $costs
     */
    protected function syncAdditionalCosts(PriceOffer $offer, array $costs, int $tenantId): void
    {
        foreach ($costs as $cost) {
            $taxProfile = TaxProfile::with('taxes')->find($cost['tax_profile_id'] ?? null);

            $offer->additionalCosts()->create([
                'tenant_id' => $tenantId,
                'additional_cost_type_id' => $cost['additional_cost_type_id'],
                'statement' => $cost['statement'],
                'cost' => $cost['cost'],
                'tax_profile_id' => $taxProfile?->id,
                'tax_profile_data' => $taxProfile?->toArray(),
            ]);
        }
    }

    protected function deleteDetails(PriceOffer $offer): void
    {
        $offer->loadMissing('details.offerDetailsExtras');

        foreach ($offer->details as $detail) {
            $detail->offerDetailsExtras()->delete();
            $detail->delete();
        }
    }

    /**
     * @param  array{product_id: int, product_variant_id: int|null, name: string}  $resolved
     * @return array{item_id: int, item_type: class-string}
     */
    protected function morphItem(array $resolved): array
    {
        if (! empty($resolved['product_variant_id'])) {
            return [
                'item_id' => (int) $resolved['product_variant_id'],
                'item_type' => ProductVariant::class,
            ];
        }

        return [
            'item_id' => (int) $resolved['product_id'],
            'item_type' => Product::class,
        ];
    }
}
