<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SupplyOrder;
use App\Models\SupplyOrderDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplyOrderService
{
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

            return $order->fresh()->load(['supplier.acc4', 'supplier.state', 'supplier.city.state', 'supplier.area', 'details.item', 'tenant']);
        });
    }

    public function update(SupplyOrder $order, array $payload, int $tenantId, int $userId): SupplyOrder
    {
        return DB::transaction(function () use ($order, $payload, $tenantId, $userId) {
            $order->update(array_filter([
                'supplier_id' => $payload['supplier_id'] ?? null,
                'description' => $payload['description'] ?? null,
            ], fn ($value) => $value !== null));

            if (array_key_exists('details', $payload)) {
                $order->details()->delete();
                $this->syncDetails($order, $payload['details'], $tenantId, $userId);
            }

            return $order->fresh()->load(['supplier.acc4', 'supplier.state', 'supplier.city.state', 'supplier.area', 'details.item', 'tenant']);
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
            $resolved = $this->resolveItem($detail);

            SupplyOrderDetails::create([
                'tenant_id' => $tenantId,
                'supply_order_id' => $order->id,
                'user_id' => $userId,
                'item_id' => $resolved['item_id'],
                'item_type' => $resolved['item_type'],
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
     * @param  array<string, mixed>  $detail
     * @return array{item_id: int, item_type: class-string}
     */
    public function resolveItem(array $detail): array
    {
        if (! empty($detail['product_variant_id'])) {
            $variant = ProductVariant::query()->findOrFail($detail['product_variant_id']);

            if (! empty($detail['product_id']) && (int) $variant->product_id !== (int) $detail['product_id']) {
                throw ValidationException::withMessages([
                    'details' => __('validation.exists', ['attribute' => 'product_variant_id']),
                ]);
            }

            return [
                'item_id' => $variant->id,
                'item_type' => ProductVariant::class,
            ];
        }

        return [
            'item_id' => (int) $detail['product_id'],
            'item_type' => Product::class,
        ];
    }
}
