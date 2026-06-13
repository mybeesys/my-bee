<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ItemStock;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchasesReturnsDetails;
use App\Models\SalesReturnsDetails;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InventoryReportService
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SALES = 'sales';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    public const TYPE_PURCHASE_RETURN = 'purchase_return';

    public const TYPE_SALES_RETURN = 'sales_return';

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     warehouse_ids?: array<int>|null,
     *     product_ids?: array<int>|null,
     *     movement_types?: array<string>|null,
     * }  $filters
     * @return array{rows: array<int, array<string, mixed>>, filters: array<string, mixed>}
     */
    public function buildSummary(array $filters): array
    {
        $from = $this->parseDate($filters['from'] ?? null)?->startOfDay();
        $to = $this->parseDate($filters['to'] ?? null)?->endOfDay();
        $warehouseIds = collect($filters['warehouse_ids'] ?? [])->filter()->values()->all();
        $productIds = collect($filters['product_ids'] ?? [])->filter()->values()->all();
        $movementTypes = collect($filters['movement_types'] ?? [])->filter()->values()->all();

        $balances = $this->currentBalances($warehouseIds, $productIds);
        $keys = $this->mergeKeys(
            collect(array_keys($balances)),
            $this->periodMovementKeys($from, $to, $warehouseIds, $productIds),
        );

        $products = Product::query()
            ->when($productIds !== [], fn ($q) => $q->whereIn('id', $productIds))
            ->get(['id', 'name', 'sku'])
            ->keyBy('id');

        $warehouses = Warehouse::query()
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('id', $warehouseIds))
            ->get(['id', 'name'])
            ->keyBy('id');

        $purchases = $this->purchasesInPeriod($from, $to, $warehouseIds, $productIds);
        $sales = $this->salesInPeriod($from, $to, $warehouseIds, $productIds);
        $transfersIn = $this->transfersInPeriod($from, $to, $warehouseIds, $productIds, inbound: true);
        $transfersOut = $this->transfersInPeriod($from, $to, $warehouseIds, $productIds, inbound: false);
        $purchaseReturns = $this->purchaseReturnsInPeriod($from, $to, $warehouseIds, $productIds);
        $salesReturns = $this->salesReturnsInPeriod($from, $to, $warehouseIds, $productIds);
        $openingStock = $this->openingStockInPeriod($from, $to, $warehouseIds, $productIds);

        $rows = [];

        foreach ($keys as $key) {
            [$productId, $warehouseId] = $this->parseKey($key);

            if (! $products->has($productId) || ! $warehouses->has($warehouseId)) {
                continue;
            }

            $openingEntries = (float) ($openingStock[$key] ?? 0);

            $row = [
                'key' => $key,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'sku' => $products[$productId]->sku,
                'product_name' => $products[$productId]->name,
                'warehouse_name' => $warehouses[$warehouseId]->name,
                'opening_stock_entries' => $openingEntries,
                'opening_inventory' => 0.0,
                'purchased_quantity' => (float) ($purchases[$key] ?? 0),
                'sales_quantity' => (float) ($sales[$key] ?? 0),
                'waste' => 0.0,
                'purchase_returns' => (float) ($purchaseReturns[$key] ?? 0),
                'sales_returns' => (float) ($salesReturns[$key] ?? 0),
                'transferred_quantity' => (float) ($transfersIn[$key] ?? 0),
                'transferred_out_quantity' => (float) ($transfersOut[$key] ?? 0),
                'production_quantity' => 0.0,
                'counted_quantity' => 0.0,
                'quantity_on_inventory' => (float) ($balances[$key] ?? 0),
            ];

            if ($from && $to) {
                $row['opening_inventory'] = $this->deriveOpeningBalance($row);
            }

            if ($movementTypes !== [] && ! $this->rowMatchesMovementFilter($row, $movementTypes)) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => [$a['product_name'], $a['warehouse_name']] <=> [$b['product_name'], $b['warehouse_name']]);

        return [
            'rows' => $rows,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'warehouse_ids' => $warehouseIds,
                'product_ids' => $productIds,
                'movement_types' => $movementTypes,
            ],
        ];
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     product_id?: int|null,
     *     warehouse_id?: int|null,
     *     movement_types?: array<string>|null,
     *     summary?: array<string, mixed>|null,
     * }  $filters
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     product: ?Product,
     *     warehouse: ?Warehouse,
     *     summary: ?array<string, mixed>,
     *     filters: array<string, mixed>
     * }
     */
    public function buildDetail(array $filters): array
    {
        $from = $this->parseDate($filters['from'] ?? null)?->startOfDay();
        $to = $this->parseDate($filters['to'] ?? null)?->endOfDay();
        $productId = (int) ($filters['product_id'] ?? 0) ?: null;
        $warehouseId = (int) ($filters['warehouse_id'] ?? 0) ?: null;
        $movementTypes = collect($filters['movement_types'] ?? [])->filter()->values()->all();

        $warehouseIds = $warehouseId ? [$warehouseId] : [];
        $productIds = $productId ? [$productId] : [];

        $lines = collect()
            ->merge($this->detailOpeningLines($from, $to, $warehouseIds, $productIds))
            ->merge($this->detailPurchaseLines($from, $to, $warehouseIds, $productIds))
            ->merge($this->detailSalesLines($from, $to, $warehouseIds, $productIds))
            ->merge($this->detailTransferLines($from, $to, $warehouseIds, $productIds))
            ->merge($this->detailPurchaseReturnLines($from, $to, $warehouseIds, $productIds))
            ->merge($this->detailSalesReturnLines($from, $to, $warehouseIds, $productIds))
            ->sortBy([
                ['sort_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();

        $stats = ($productId && $warehouseId)
            ? $this->buildDetailStats($from, $to, $productId, $warehouseId)
            : null;

        $lines = $this->applyRunningBalances($lines, (float) ($stats['opening_inventory'] ?? 0));

        $lines = collect($lines)
            ->when($movementTypes !== [], fn (Collection $c) => $c->filter(
                fn (array $line) => in_array($line['movement_type'], $movementTypes, true)
            ))
            ->values()
            ->all();

        $lines = $this->enrichDetailLines($lines);
        $lines = $this->sortDetailLinesNewestFirst($lines);

        return [
            'lines' => $lines,
            'product' => $productId ? Product::find($productId) : null,
            'warehouse' => $warehouseId ? Warehouse::find($warehouseId) : null,
            'stats' => $stats,
            'summary' => $filters['summary'] ?? null,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'movement_types' => $movementTypes,
            ],
        ];
    }

    /** @return array<string, float> */
    protected function currentBalances(array $warehouseIds, array $productIds): array
    {
        return ItemStock::query()
            ->selectRaw('product_id, warehouse_id, SUM(qty_in - qty_out - qty_moved) as qty')
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereNotNull('warehouse_id')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->key($row->product_id, $row->warehouse_id) => (float) $row->qty])
            ->all();
    }

    /** @return Collection<int, string> */
    protected function periodMovementKeys(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): Collection
    {
        if (! $from || ! $to) {
            return collect();
        }

        return collect()
            ->merge(array_keys($this->purchasesInPeriod($from, $to, $warehouseIds, $productIds)))
            ->merge(array_keys($this->salesInPeriod($from, $to, $warehouseIds, $productIds)))
            ->merge(array_keys($this->transfersInPeriod($from, $to, $warehouseIds, $productIds, true)))
            ->merge(array_keys($this->transfersInPeriod($from, $to, $warehouseIds, $productIds, false)))
            ->merge(array_keys($this->purchaseReturnsInPeriod($from, $to, $warehouseIds, $productIds)))
            ->merge(array_keys($this->salesReturnsInPeriod($from, $to, $warehouseIds, $productIds)))
            ->merge(array_keys($this->openingStockInPeriod($from, $to, $warehouseIds, $productIds)))
            ->unique();
    }

    /** @return array<int, string> */
    protected function purchaseInvoiceStatuses(): array
    {
        return ['confirmed', 'purchase_order'];
    }

    protected function applyPurchaseInvoicePeriodFilter($query, ?Carbon $from, ?Carbon $to): void
    {
        $query->where('type', 'purchases')
            ->whereIn('status', $this->purchaseInvoiceStatuses())
            ->where(function ($periodQuery) use ($from, $to) {
                $periodQuery->whereBetween('locked_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('locked_at')
                            ->whereBetween('created_at', [$from, $to]);
                    });
            });
    }

    protected function resolvePurchaseWarehouse(InvoiceItem $item): ?int
    {
        $warehouseId = $item->warehouse_id ?? $item->invoice?->warehouse_id;

        return $warehouseId ? (int) $warehouseId : null;
    }

    /** @return array<string, float> */
    protected function purchasesInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        InvoiceItem::query()
            ->with(['invoice'])
            ->where('cancelled', false)
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereHas('invoice', fn ($q) => $this->applyPurchaseInvoicePeriodFilter($q, $from, $to))
            ->get()
            ->each(function (InvoiceItem $item) use (&$totals, $warehouseIds) {
                $warehouseId = $this->resolvePurchaseWarehouse($item);

                if (! $warehouseId) {
                    return;
                }

                if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                    return;
                }

                $qty = $this->invoiceItemOriginalQty($item);

                if ($qty <= 0) {
                    return;
                }

                $key = $this->key($item->product_id, $warehouseId);
                $totals[$key] = ($totals[$key] ?? 0) + $qty;
            });

        return $totals;
    }

    /** @return array<int, string> */
    protected function salesInvoiceStatuses(): array
    {
        return ['confirmed'];
    }

    protected function applySalesInvoicePeriodFilter($query, ?Carbon $from, ?Carbon $to): void
    {
        $query->where('type', 'sales')
            ->whereIn('status', $this->salesInvoiceStatuses())
            ->where(function ($periodQuery) use ($from, $to) {
                $periodQuery->whereBetween('locked_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('locked_at')
                            ->whereBetween('created_at', [$from, $to]);
                    });
            });
    }

    /** @return array<string, float> */
    protected function salesInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        InvoiceItem::query()
            ->with(['invoice'])
            ->where('cancelled', false)
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereHas('invoice', fn ($q) => $this->applySalesInvoicePeriodFilter($q, $from, $to))
            ->get()
            ->each(function (InvoiceItem $item) use (&$totals, $warehouseIds) {
                $qty = $this->invoiceItemOriginalQty($item);

                if ($qty <= 0) {
                    return;
                }

                foreach ($this->resolveSalesWarehouses($item, $qty) as $warehouseId => $warehouseQty) {
                    if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                        continue;
                    }

                    $key = $this->key($item->product_id, $warehouseId);
                    $totals[$key] = ($totals[$key] ?? 0) + (float) $warehouseQty;
                }
            });

        return $totals;
    }

    /** @return array<string, float> */
    protected function transfersInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds, bool $inbound): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        if ($inbound) {
            ItemStock::query()
                ->where('type', 'moved')
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
                ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
                ->get(['product_id', 'warehouse_id', 'qty_in'])
                ->each(function (ItemStock $stock) use (&$totals) {
                    $key = $this->key($stock->product_id, $stock->warehouse_id);
                    $totals[$key] = ($totals[$key] ?? 0) + (float) $stock->qty_in;
                });

            return $totals;
        }

        StockMovement::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('target_warehouse_id', $warehouseIds))
            ->get()
            ->each(function (StockMovement $movement) use (&$totals, $productIds) {
                $productId = $movement->item_type === Product::class ? $movement->item_id : null;

                if (! $productId) {
                    return;
                }

                if ($productIds !== [] && ! in_array($productId, $productIds, true)) {
                    return;
                }

                $key = $this->key($productId, $movement->target_warehouse_id);
                $totals[$key] = ($totals[$key] ?? 0) + (float) $movement->qty;
            });

        return $totals;
    }

    /** @return array<string, float> */
    protected function purchaseReturnsInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        PurchasesReturnsDetails::query()
            ->with(['invoiceItem.invoice'])
            ->whereBetween('created_at', [$from, $to])
            ->when($productIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem',
                fn ($inner) => $inner->whereIn('product_id', $productIds)
            ))
            ->get()
            ->each(function (PurchasesReturnsDetails $detail) use (&$totals, $warehouseIds) {
                $item = $detail->invoiceItem;
                $warehouseId = $item?->invoice?->warehouse_id;

                if (! $item || ! $warehouseId) {
                    return;
                }

                if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                    return;
                }

                $key = $this->key($item->product_id, $warehouseId);
                $totals[$key] = ($totals[$key] ?? 0) + (float) $detail->qty;
            });

        return $totals;
    }

    /** @return array<string, float> */
    protected function salesReturnsInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        SalesReturnsDetails::query()
            ->with(['invoiceItem.invoice'])
            ->whereBetween('created_at', [$from, $to])
            ->when($productIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem',
                fn ($inner) => $inner->whereIn('product_id', $productIds)
            ))
            ->get()
            ->each(function (SalesReturnsDetails $detail) use (&$totals, $warehouseIds) {
                $item = $detail->invoiceItem;

                if (! $item) {
                    return;
                }

                foreach ($this->resolveSalesWarehouses($item, (float) $detail->qty) as $warehouseId => $qty) {
                    if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                        continue;
                    }

                    $key = $this->key($item->product_id, $warehouseId);
                    $totals[$key] = ($totals[$key] ?? 0) + (float) $qty;
                }
            });

        return $totals;
    }

    /** @return array<string, float> */
    protected function openingStockInPeriod(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $totals = [];

        ItemStock::query()
            ->where('type', 'opening-stock')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->get(['product_id', 'warehouse_id', 'qty_in'])
            ->each(function (ItemStock $stock) use (&$totals) {
                $key = $this->key($stock->product_id, $stock->warehouse_id);
                $totals[$key] = ($totals[$key] ?? 0) + (float) $stock->qty_in;
            });

        return $totals;
    }

    /** @param  array<string, mixed>  $row */
    protected function deriveOpeningBalance(array $row): float
    {
        return (float) $row['quantity_on_inventory']
            - (float) $row['purchased_quantity']
            - (float) $row['opening_stock_entries']
            - (float) $row['transferred_quantity']
            + (float) $row['sales_quantity']
            + (float) $row['purchase_returns']
            + (float) $row['transferred_out_quantity']
            - (float) ($row['sales_returns'] ?? 0);
    }

    /** @param  array<string, mixed>  $row  @param  array<int, string>  $movementTypes */
    protected function rowMatchesMovementFilter(array $row, array $movementTypes): bool
    {
        foreach ($movementTypes as $type) {
            $value = match ($type) {
                self::TYPE_OPENING => ($row['opening_inventory'] ?? 0) + ($row['opening_stock_entries'] ?? 0),
                self::TYPE_PURCHASE => $row['purchased_quantity'],
                self::TYPE_SALES => $row['sales_quantity'],
                self::TYPE_TRANSFER_IN => $row['transferred_quantity'],
                self::TYPE_TRANSFER_OUT => $row['transferred_out_quantity'],
                self::TYPE_PURCHASE_RETURN => $row['purchase_returns'],
                self::TYPE_SALES_RETURN => $row['sales_returns'] ?? 0,
                default => 0,
            };

            if ((float) $value !== 0.0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailOpeningLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        return ItemStock::query()
            ->where('type', 'opening-stock')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->get()
            ->map(fn (ItemStock $stock) => $this->lineFromItemStock($stock, self::TYPE_OPENING, (float) $stock->qty_in))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailPurchaseLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $lines = [];

        InvoiceItem::query()
            ->with(['invoice.supplier'])
            ->where('cancelled', false)
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereHas('invoice', fn ($q) => $this->applyPurchaseInvoicePeriodFilter($q, $from, $to))
            ->get()
            ->each(function (InvoiceItem $item) use (&$lines, $warehouseIds) {
                $warehouseId = $this->resolvePurchaseWarehouse($item);

                if (! $warehouseId) {
                    return;
                }

                if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                    return;
                }

                $qty = $this->invoiceItemOriginalQty($item);

                if ($qty <= 0) {
                    return;
                }

                $lines[] = array_merge([
                    'id' => 'purchase-' . $item->id . '-' . $warehouseId,
                    'movement_type' => self::TYPE_PURCHASE,
                    'movement_label' => __('fields.inventory_movement_purchase'),
                    'quantity' => abs($qty),
                    'party' => $item->invoice->supplier?->name,
                    'reference' => $item->invoice->no,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                ], $this->detailLineDates($item->invoice->locked_at ?? $item->invoice->created_at));
            });

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailSalesLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $lines = [];

        InvoiceItem::query()
            ->with(['invoice.customer'])
            ->where('cancelled', false)
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereHas('invoice', fn ($q) => $this->applySalesInvoicePeriodFilter($q, $from, $to))
            ->get()
            ->each(function (InvoiceItem $item) use (&$lines, $warehouseIds) {
                $qty = $this->invoiceItemOriginalQty($item);

                if ($qty <= 0) {
                    return;
                }

                $occurredAt = $item->invoice->locked_at ?? $item->invoice->created_at;

                foreach ($this->resolveSalesWarehouses($item, $qty) as $warehouseId => $warehouseQty) {
                    if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                        continue;
                    }

                    $lines[] = array_merge([
                        'id' => 'sales-' . $item->id . '-' . $warehouseId,
                        'movement_type' => self::TYPE_SALES,
                        'movement_label' => __('fields.inventory_movement_sales'),
                        'quantity' => -1 * abs((float) $warehouseQty),
                        'party' => $item->invoice->customer?->name,
                        'reference' => $item->invoice->no,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                    ], $this->detailLineDates($occurredAt));
                }
            });

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailTransferLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $lines = [];

        ItemStock::query()
            ->with(['warehouse'])
            ->where('type', 'moved')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->get()
            ->each(function (ItemStock $stock) use (&$lines) {
                $lines[] = $this->lineFromItemStock($stock, self::TYPE_TRANSFER_IN, (float) $stock->qty_in, $stock->warehouse?->name);
            });

        StockMovement::query()
            ->with(['destinationWarehouse'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($warehouseIds !== [], fn ($q) => $q->whereIn('target_warehouse_id', $warehouseIds))
            ->get()
            ->each(function (StockMovement $movement) use (&$lines, $productIds) {
                $productId = $movement->item_type === Product::class ? $movement->item_id : null;

                if (! $productId || ($productIds !== [] && ! in_array($productId, $productIds, true))) {
                    return;
                }

                $lines[] = array_merge([
                    'id' => 'transfer-out-' . $movement->id,
                    'movement_type' => self::TYPE_TRANSFER_OUT,
                    'movement_label' => __('fields.inventory_movement_transfer_out'),
                    'quantity' => -1 * abs((float) $movement->qty),
                    'party' => $movement->destinationWarehouse?->name,
                    'reference' => '#' . $movement->id,
                    'product_id' => $productId,
                    'warehouse_id' => $movement->target_warehouse_id,
                ], $this->detailLineDates($movement->date));
            });

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailPurchaseReturnLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $lines = [];

        PurchasesReturnsDetails::query()
            ->with(['invoiceItem.invoice.supplier'])
            ->whereBetween('created_at', [$from, $to])
            ->when($productIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem',
                fn ($inner) => $inner->whereIn('product_id', $productIds)
            ))
            ->get()
            ->each(function (PurchasesReturnsDetails $detail) use (&$lines, $warehouseIds) {
                $item = $detail->invoiceItem;
                $warehouseId = $item?->invoice?->warehouse_id;

                if (! $item || ! $warehouseId) {
                    return;
                }

                if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                    return;
                }

                $lines[] = array_merge([
                    'id' => 'purchase-return-' . $detail->id,
                    'movement_type' => self::TYPE_PURCHASE_RETURN,
                    'movement_label' => __('fields.inventory_movement_purchase_return'),
                    'quantity' => -1 * abs((float) $detail->qty),
                    'party' => $item->invoice?->supplier?->name,
                    'reference' => $item->invoice?->no,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouseId,
                ], $this->detailLineDates($detail->created_at));
            });

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    protected function detailSalesReturnLines(?Carbon $from, ?Carbon $to, array $warehouseIds, array $productIds): array
    {
        if (! $from || ! $to) {
            return [];
        }

        $lines = [];

        SalesReturnsDetails::query()
            ->with(['invoiceItem.invoice.customer'])
            ->whereBetween('created_at', [$from, $to])
            ->when($productIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem',
                fn ($inner) => $inner->whereIn('product_id', $productIds)
            ))
            ->get()
            ->each(function (SalesReturnsDetails $detail) use (&$lines, $warehouseIds) {
                $item = $detail->invoiceItem;

                if (! $item) {
                    return;
                }

                foreach ($this->resolveSalesWarehouses($item, (float) $detail->qty) as $warehouseId => $qty) {
                    if ($warehouseIds !== [] && ! in_array($warehouseId, $warehouseIds, true)) {
                        continue;
                    }

                    $lines[] = array_merge([
                        'id' => 'sales-return-' . $detail->id . '-' . $warehouseId,
                        'movement_type' => self::TYPE_SALES_RETURN,
                        'movement_label' => __('fields.inventory_movement_sales_return'),
                        'quantity' => abs((float) $qty),
                        'party' => $item->invoice?->customer?->name,
                        'reference' => $item->invoice?->no,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                    ], $this->detailLineDates($detail->created_at));
                }
            });

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetailStats(?Carbon $from, ?Carbon $to, int $productId, int $warehouseId): array
    {
        $warehouseIds = [$warehouseId];
        $productIds = [$productId];
        $key = $this->key($productId, $warehouseId);

        $purchases = $this->purchasesInPeriod($from, $to, $warehouseIds, $productIds);
        $sales = $this->salesInPeriod($from, $to, $warehouseIds, $productIds);
        $transfersIn = $this->transfersInPeriod($from, $to, $warehouseIds, $productIds, inbound: true);
        $transfersOut = $this->transfersInPeriod($from, $to, $warehouseIds, $productIds, inbound: false);
        $purchaseReturns = $this->purchaseReturnsInPeriod($from, $to, $warehouseIds, $productIds);
        $salesReturns = $this->salesReturnsInPeriod($from, $to, $warehouseIds, $productIds);
        $openingStock = $this->openingStockInPeriod($from, $to, $warehouseIds, $productIds);
        $balances = $this->currentBalances($warehouseIds, $productIds);

        $quantityOnInventory = (float) ($balances[$key] ?? 0);

        if (! ItemStock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->exists()
        ) {
            $product = Product::find($productId);

            if ($product) {
                $quantityOnInventory = (float) StockService::instance()->getAvailableStock($product);
            }
        }

        $row = [
            'opening_stock_entries' => (float) ($openingStock[$key] ?? 0),
            'purchased_quantity' => (float) ($purchases[$key] ?? 0),
            'sales_quantity' => (float) ($sales[$key] ?? 0),
            'purchase_returns' => (float) ($purchaseReturns[$key] ?? 0),
            'sales_returns' => (float) ($salesReturns[$key] ?? 0),
            'transferred_quantity' => (float) ($transfersIn[$key] ?? 0),
            'transferred_out_quantity' => (float) ($transfersOut[$key] ?? 0),
            'quantity_on_inventory' => $quantityOnInventory,
        ];

        $row['opening_inventory'] = ($from && $to)
            ? $this->deriveOpeningBalance(array_merge($row, [
                'waste' => 0,
                'production_quantity' => 0,
                'counted_quantity' => 0,
            ]))
            : (float) ($balances[$key] ?? 0);

        $unitName = $this->productUnitName($productId);

        return [
            'opening_inventory' => $row['opening_inventory'],
            'purchased_quantity' => $row['purchased_quantity'],
            'sales_quantity' => $row['sales_quantity'],
            'waste' => 0.0,
            'purchase_returns' => $row['purchase_returns'],
            'transferred_quantity' => $row['transferred_quantity'],
            'production_quantity' => 0.0,
            'counted_quantity' => 0.0,
            'quantity_on_inventory' => $row['quantity_on_inventory'],
            'unit_name' => $unitName,
        ];
    }

    /** @return array<string, float> */
    protected function resolveSalesWarehouses(InvoiceItem $item, ?float $qtyOverride = null): array
    {
        $qty = $qtyOverride ?? $this->invoiceItemOriginalQty($item);
        $stockIds = collect($item->stocks ?? [])->filter()->values();

        if ($stockIds->isNotEmpty()) {
            $stocks = ItemStock::query()
                ->whereIn('id', $stockIds->all())
                ->get(['id', 'warehouse_id']);

            if ($stocks->isNotEmpty()) {
                $byWarehouse = $stocks->groupBy('warehouse_id');

                if ($byWarehouse->count() === 1) {
                    return [(int) $byWarehouse->keys()->first() => $qty];
                }

                $share = $qty / $byWarehouse->count();
                $result = [];

                foreach ($byWarehouse as $warehouseId => $group) {
                    $result[(int) $warehouseId] = ($result[(int) $warehouseId] ?? 0) + $share;
                }

                return $result;
            }
        }

        $warehouseId = $item->warehouse_id
            ?? $item->invoice?->warehouse_id
            ?? Warehouse::query()->where('main', true)->value('id')
            ?? Warehouse::query()->value('id');

        return $warehouseId ? [(int) $warehouseId => $qty] : [];
    }

    protected function lineFromItemStock(
        ItemStock $stock,
        string $movementType,
        float $quantity,
        ?string $party = null,
        ?string $reference = null,
    ): array {
        return array_merge([
            'id' => 'stock-' . $stock->id,
            'movement_type' => $movementType,
            'movement_label' => $this->movementLabel($movementType),
            'quantity' => $quantity,
            'party' => $party,
            'reference' => $reference ?? $stock->no,
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
        ], $this->detailLineDates($stock->date ?? $stock->created_at));
    }

    /** @param  array<int, array<string, mixed>>  $lines  @return array<int, array<string, mixed>> */
    protected function sortDetailLinesNewestFirst(array $lines): array
    {
        return collect($lines)
            ->sort($this->newestFirstDetailLineSorter())
            ->values()
            ->all();
    }

    protected function newestFirstDetailLineSorter(): callable
    {
        return function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['sort_at'] ?? $b['date'] ?? ''), (string) ($a['sort_at'] ?? $a['date'] ?? ''));

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
        };
    }

    /** @return array{date: ?string, sort_at: ?string} */
    protected function detailLineDates(mixed $value): array
    {
        if (blank($value)) {
            return ['date' => null, 'sort_at' => null];
        }

        $carbon = Carbon::parse($value);

        return [
            'date' => $carbon->toDateString(),
            'sort_at' => $carbon->toDateTimeString(),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $lines  @return array<int, array<string, mixed>> */
    protected function applyRunningBalances(array $lines, float $openingBalance = 0.0): array
    {
        if ($lines === []) {
            return [];
        }

        $balance = $openingBalance;

        return collect($lines)->map(function (array $line) use (&$balance) {
            $balance += (float) ($line['quantity'] ?? 0);
            $line['balance_after'] = $balance;

            return $line;
        })->all();
    }

    /** @param  array<int, array<string, mixed>>  $lines  @return array<int, array<string, mixed>> */
    protected function enrichDetailLines(array $lines): array
    {
        if ($lines === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', collect($lines)->pluck('product_id')->unique()->filter())
            ->get(['id', 'name'])
            ->keyBy('id');

        $warehouses = Warehouse::query()
            ->whereIn('id', collect($lines)->pluck('warehouse_id')->unique()->filter())
            ->pluck('name', 'id');

        $unitNames = $this->productUnitNames(
            collect($lines)->pluck('product_id')->unique()->filter()->all()
        );

        return collect($lines)->map(function (array $line) use ($products, $warehouses, $unitNames) {
            $product = $products->get($line['product_id']);
            $unitName = $unitNames[$line['product_id']] ?? '';
            $qty = abs((float) ($line['quantity'] ?? 0));
            $isOutbound = $this->isOutboundMovement($line['movement_type'], (float) ($line['quantity'] ?? 0));

            return array_merge($line, [
                'product_name' => $product?->name,
                'warehouse_name' => $warehouses[$line['warehouse_id']] ?? null,
                'transfer_direction' => $isOutbound ? 'out' : 'in',
                'transfer_direction_label' => $isOutbound
                    ? __('fields.inventory_transfer_out_of_stock')
                    : __('fields.inventory_transfer_into_stock'),
                'unit_name' => $unitName,
                'quantity_abs' => $qty,
                'quantity_display' => trim($qty . ($unitName !== '' ? ' ' . $unitName : '')),
                'balance_after_display' => trim(
                    number_format((float) ($line['balance_after'] ?? 0), 2)
                    . ($unitName !== '' ? ' ' . $unitName : '')
                ),
            ]);
        })->all();
    }

    protected function isOutboundMovement(string $movementType, float $quantity): bool
    {
        return match ($movementType) {
            self::TYPE_SALES, self::TYPE_PURCHASE_RETURN, self::TYPE_TRANSFER_OUT => true,
            self::TYPE_PURCHASE, self::TYPE_OPENING, self::TYPE_TRANSFER_IN, self::TYPE_SALES_RETURN => false,
            default => $quantity < 0,
        };
    }

    protected function movementLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_OPENING => __('fields.inventory_movement_opening'),
            self::TYPE_PURCHASE => __('fields.inventory_movement_purchase'),
            self::TYPE_SALES => __('fields.inventory_movement_sales'),
            self::TYPE_TRANSFER_IN => __('fields.inventory_movement_transfer_in'),
            self::TYPE_TRANSFER_OUT => __('fields.inventory_movement_transfer_out'),
            self::TYPE_PURCHASE_RETURN => __('fields.inventory_movement_purchase_return'),
            self::TYPE_SALES_RETURN => __('fields.inventory_movement_sales_return'),
            default => $type,
        };
    }

    protected function key(int $productId, int $warehouseId): string
    {
        return $productId . ':' . $warehouseId;
    }

    /** @return array{0: int, 1: int} */
    protected function parseKey(string $key): array
    {
        [$productId, $warehouseId] = explode(':', $key, 2);

        return [(int) $productId, (int) $warehouseId];
    }

    /** @param  Collection<int, string>|array<int, string>  $keys */
    protected function mergeKeys(Collection|array $keys, Collection|array $more): Collection
    {
        return collect($keys)->merge($more)->unique()->values();
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function formatLineDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    protected function invoiceItemOriginalQty(InvoiceItem $item): float
    {
        $raw = $item->getRawOriginal('qty');

        if ($raw !== null) {
            return (float) $raw;
        }

        return (float) ($item->getAttributes()['qty'] ?? 0);
    }

    protected function productUnitName(int $productId): string
    {
        return $this->productUnitNames([$productId])[$productId] ?? '';
    }

    /**
     * @param  array<int, int>  $productIds
     * @return array<int, string>
     */
    protected function productUnitNames(array $productIds): array
    {
        if ($productIds === [] || ! Schema::hasTable('product_unit')) {
            return [];
        }

        return ProductUnit::query()
            ->whereIn('product_id', $productIds)
            ->with('unit:id,name')
            ->orderByDesc('main')
            ->get()
            ->unique('product_id')
            ->mapWithKeys(fn (ProductUnit $productUnit) => [
                $productUnit->product_id => $productUnit->unit?->name ?? '',
            ])
            ->all();
    }
}
