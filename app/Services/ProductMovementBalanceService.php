<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ItemStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchasesReturnsDetails;
use App\Models\SalesReturnsDetails;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductMovementBalanceService
{
    /** @var array<string, array<string, float>> */
    protected array $balanceCache = [];

    public function balanceAfter(InvoiceItem $item): float
    {
        $item->loadMissing('invoice');

        $balances = $this->balancesForKey($this->stockKey($item));

        return (float) ($balances['invoice-item-' . $item->id] ?? 0);
    }

    /** @param  iterable<InvoiceItem>  $items */
    public function preloadForItems(iterable $items): void
    {
        foreach ($items as $item) {
            $this->balancesForKey($this->stockKey($item));
        }
    }

    protected function stockKey(InvoiceItem $item): string
    {
        return ($item->product_id ?? 0) . ':' . ($item->product_variant_id ?? 0);
    }

    /** @return array<string, float> */
    protected function balancesForKey(string $key): array
    {
        if (isset($this->balanceCache[$key])) {
            return $this->balanceCache[$key];
        }

        [$productId, $variantId] = array_map('intval', explode(':', $key, 2));

        if ($productId <= 0) {
            return $this->balanceCache[$key] = [];
        }

        $events = collect()
            ->merge($this->openingStockEvents($productId, $variantId))
            ->merge($this->purchaseEvents($productId, $variantId))
            ->merge($this->salesEvents($productId, $variantId))
            ->merge($this->purchaseReturnEvents($productId, $variantId))
            ->merge($this->salesReturnEvents($productId, $variantId))
            ->sortBy([
                ['sort_at', 'asc'],
                ['tie', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $balance = 0.0;
        $balances = [];

        foreach ($events as $event) {
            $balance += (float) $event['delta'];
            $balances[$event['id']] = $balance;
        }

        return $this->balanceCache[$key] = $balances;
    }

  /** @return Collection<int, array{id: string, sort_at: string, delta: float, tie: int|string}> */
    protected function openingStockEvents(int $productId, int $variantId): Collection
    {
        return ItemStock::query()
            ->where('type', 'opening-stock')
            ->where('product_id', $productId)
            ->when($variantId > 0, fn ($query) => $query
                ->where('item_id', $variantId)
                ->where('item_type', ProductVariant::class))
            ->when($variantId === 0, fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('item_type')->orWhere('item_type', Product::class);
            }))
            ->get()
            ->map(fn (ItemStock $stock) => [
                'id' => 'opening-' . $stock->id,
                'sort_at' => Carbon::parse($stock->date ?? $stock->created_at)->toDateTimeString(),
                'delta' => (float) $stock->qty_in,
                'tie' => 'opening-' . $stock->id,
            ]);
    }

    /** @return Collection<int, array{id: string, sort_at: string, delta: float, tie: int|string}> */
    protected function purchaseEvents(int $productId, int $variantId): Collection
    {
        return InvoiceItem::query()
            ->with('invoice')
            ->where('product_id', $productId)
            ->where('cancelled', false)
            ->when($variantId > 0, fn ($query) => $query->where('product_variant_id', $variantId))
            ->when($variantId === 0, fn ($query) => $query->whereNull('product_variant_id'))
            ->whereHas('invoice', fn ($query) => $query
                ->where('type', 'purchases')
                ->whereIn('status', ['confirmed', 'purchase_order']))
            ->get()
            ->map(function (InvoiceItem $item) {
                $qty = $this->invoiceItemRawQty($item);

                if ($qty <= 0) {
                    return null;
                }

                return [
                    'id' => 'invoice-item-' . $item->id,
                    'sort_at' => $this->invoiceItemSortAt($item),
                    'delta' => $qty,
                    'tie' => $item->id,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{id: string, sort_at: string, delta: float, tie: int|string}> */
    protected function salesEvents(int $productId, int $variantId): Collection
    {
        return InvoiceItem::query()
            ->with('invoice')
            ->where('product_id', $productId)
            ->where('cancelled', false)
            ->when($variantId > 0, fn ($query) => $query->where('product_variant_id', $variantId))
            ->when($variantId === 0, fn ($query) => $query->whereNull('product_variant_id'))
            ->whereHas('invoice', fn ($query) => $query
                ->where('type', 'sales')
                ->where('status', 'confirmed'))
            ->get()
            ->map(function (InvoiceItem $item) {
                $qty = $this->invoiceItemRawQty($item);

                if ($qty <= 0) {
                    return null;
                }

                return [
                    'id' => 'invoice-item-' . $item->id,
                    'sort_at' => $this->invoiceItemSortAt($item),
                    'delta' => -1 * $qty,
                    'tie' => $item->id,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{id: string, sort_at: string, delta: float, tie: int|string}> */
    protected function purchaseReturnEvents(int $productId, int $variantId): Collection
    {
        return PurchasesReturnsDetails::query()
            ->with(['invoiceItem.invoice'])
            ->whereHas('invoiceItem', function ($query) use ($productId, $variantId) {
                $query->where('product_id', $productId)
                    ->when($variantId > 0, fn ($inner) => $inner->where('product_variant_id', $variantId))
                    ->when($variantId === 0, fn ($inner) => $inner->whereNull('product_variant_id'));
            })
            ->get()
            ->map(function (PurchasesReturnsDetails $detail) {
                $qty = (float) $detail->qty;

                if ($qty <= 0) {
                    return null;
                }

                return [
                    'id' => 'purchase-return-' . $detail->id,
                    'sort_at' => Carbon::parse($detail->created_at)->toDateTimeString(),
                    'delta' => -1 * $qty,
                    'tie' => 'purchase-return-' . $detail->id,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array{id: string, sort_at: string, delta: float, tie: int|string}> */
    protected function salesReturnEvents(int $productId, int $variantId): Collection
    {
        return SalesReturnsDetails::query()
            ->with(['invoiceItem.invoice'])
            ->whereHas('invoiceItem', function ($query) use ($productId, $variantId) {
                $query->where('product_id', $productId)
                    ->when($variantId > 0, fn ($inner) => $inner->where('product_variant_id', $variantId))
                    ->when($variantId === 0, fn ($inner) => $inner->whereNull('product_variant_id'));
            })
            ->get()
            ->map(function (SalesReturnsDetails $detail) {
                $qty = (float) $detail->qty;

                if ($qty <= 0) {
                    return null;
                }

                return [
                    'id' => 'sales-return-' . $detail->id,
                    'sort_at' => Carbon::parse($detail->created_at)->toDateTimeString(),
                    'delta' => $qty,
                    'tie' => 'sales-return-' . $detail->id,
                ];
            })
            ->filter()
            ->values();
    }

    protected function invoiceItemRawQty(InvoiceItem $item): float
    {
        $raw = $item->getRawOriginal('qty');

        if ($raw !== null) {
            return (float) $raw;
        }

        return (float) ($item->getAttributes()['qty'] ?? 0);
    }

    protected function invoiceItemSortAt(InvoiceItem $item): string
    {
        $occurredAt = $item->invoice->locked_at
            ?? $item->invoice->created_at
            ?? $item->created_at;

        return Carbon::parse($occurredAt)->toDateTimeString();
    }
}
