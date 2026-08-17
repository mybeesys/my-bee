<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupplyOrder;
use App\Models\SupplyOrderDetails;
use App\Services\Concerns\ResolvesInvoiceProductLines;
use Illuminate\Support\Facades\DB;

class SupplyOrderService
{
    use ResolvesInvoiceProductLines;

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'supplier.acc4',
            'supplier.state',
            'supplier.city.state',
            'supplier.area',
            'details.item',
            'tenant',
        ];
    }

    public function create(array $payload, int $tenantId, int $userId): SupplyOrder
    {
        return DB::transaction(function () use ($payload, $tenantId, $userId) {
            $order = SupplyOrder::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'supplier_id' => $payload['supplier_id'],
                'description' => $payload['description'],
            ]);

            $this->syncDetails($order, $payload['details'], $tenantId, $userId);

            return $order->fresh()->load(self::eagerLoads())->loadCount('details');
        });
    }

    public function update(SupplyOrder $order, array $payload, int $tenantId, int $userId): SupplyOrder
    {
        return DB::transaction(function () use ($order, $payload, $tenantId, $userId) {
            $header = [];

            foreach (['supplier_id', 'description'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $header[$field] = $payload[$field];
                }
            }

            if ($header !== []) {
                $order->update($header);
            }

            if (array_key_exists('details', $payload)) {
                $order->details()->delete();
                $this->syncDetails($order, $payload['details'], $tenantId, $userId);
            }

            return $order->fresh()->load(self::eagerLoads())->loadCount('details');
        });
    }

    public function delete(SupplyOrder $order): void
    {
        DB::transaction(function () use ($order) {
            $order->details()->delete();
            $order->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    protected function syncDetails(SupplyOrder $order, array $details, int $tenantId, int $userId): void
    {
        foreach ($details as $detail) {
            $resolved = $this->resolveProduct($detail, 'details');
            $morph = $this->morphItem($resolved);

            SupplyOrderDetails::create([
                'tenant_id' => $tenantId,
                'supply_order_id' => $order->id,
                'user_id' => $userId,
                'item_id' => $morph['item_id'],
                'item_type' => $morph['item_type'],
                'qty' => $detail['qty'],
            ]);
        }
    }

    /**
     * Prefill payload for POST purchases/commit (does not create an invoice).
     *
     * @return array<string, mixed>
     */
    public function purchasePrefill(SupplyOrder $order): array
    {
        $order->loadMissing(['supplier', 'details.item']);

        $items = [];

        foreach ($order->details as $detail) {
            $item = $detail->item;
            $isVariant = $item instanceof ProductVariant;
            $productId = $isVariant ? $item->product_id : $item?->id;
            $variantId = $isVariant ? $item->id : null;
            $product = $isVariant ? Product::query()->find($item->product_id) : $item;

            if (! $productId) {
                continue;
            }

            $items[] = [
                'productId' => $productId,
                'productVariantId' => $variantId,
                'name' => $item->name,
                'qty' => (int) $detail->qty,
                'unitCost' => null,
                'taxProfileId' => $product?->tax_profile_id,
            ];
        }

        return [
            'supplyOrderId' => $order->id,
            'supplyOrderNo' => $order->no,
            'description' => $order->description,
            'supplierId' => $order->supplier_id,
            'supplierName' => $order->supplier?->name,
            'items' => $items,
        ];
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
