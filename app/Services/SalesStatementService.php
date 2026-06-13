<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SalesReturnsDetails;
use Carbon\Carbon;

class SalesStatementService
{
    /** @return array<int, string> */
    public function salesInvoiceStatuses(): array
    {
        return ['confirmed'];
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     customer_ids?: array<int>|null,
     *     product_ids?: array<int>|null,
     *     line_types?: array<string>|null,
     *     group_by?: string|null,
     * }  $filters
     * @return array{
     *     lines: array<int, array<string, mixed>>,
     *     stats: array<string, mixed>,
     *     filters: array<string, mixed>,
     * }
     */
    public function build(array $filters): array
    {
        $from = $this->parseDate($filters['from'] ?? null)?->startOfDay();
        $to = $this->parseDate($filters['to'] ?? null)?->endOfDay();
        $customerIds = collect($filters['customer_ids'] ?? [])->filter()->values()->all();
        $productIds = collect($filters['product_ids'] ?? [])->filter()->values()->all();
        $lineTypes = collect($filters['line_types'] ?? [])->filter()->values()->all();
        $groupBy = ($filters['group_by'] ?? 'product') === 'invoice' ? 'invoice' : 'product';

        if (! $from || ! $to) {
            return $this->emptyReport($filters);
        }

        $includeSales = $lineTypes === [] || in_array('sale', $lineTypes, true);
        $includeReturns = $lineTypes === [] || in_array('return', $lineTypes, true);

        $salesLines = $includeSales
            ? $this->buildSalesLines($from, $to, $customerIds, $productIds)
            : [];
        $returnLines = $includeReturns
            ? $this->buildReturnLines($from, $to, $customerIds, $productIds)
            : [];

        $lines = collect($salesLines)
            ->merge($returnLines)
            ->sort($this->newestFirstSorter())
            ->values()
            ->all();

        if ($groupBy === 'invoice') {
            $lines = $this->groupLinesByInvoice($lines);
        }

        $stats = $this->buildStats($salesLines, $returnLines);

        return [
            'lines' => $lines,
            'stats' => array_merge($stats, [
                'currency' => main_currency_iso_code(),
            ]),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'customer_ids' => $customerIds,
                'product_ids' => $productIds,
                'line_types' => $lineTypes,
                'group_by' => $groupBy,
            ],
        ];
    }

    /** @param  array<int, array<string, mixed>>  $lines */
    protected function groupLinesByInvoice(array $lines): array
    {
        return collect($lines)
            ->groupBy('invoice_id')
            ->map(function ($group, $invoiceId) {
                $group = collect($group);
                $first = $group->first();
                $sales = $group->where('line_type', 'sale');
                $returns = $group->where('line_type', 'return');

                $salesQty = $sales->sum('qty');
                $returnsQty = $returns->sum('qty');
                $salesTotal = $sales->sum('total');
                $returnsTotal = $returns->sum('total');

                $lineTypes = $group->pluck('line_type')->unique()->values();
                $lineTypeLabel = match (true) {
                    $lineTypes->count() > 1 => __('fields.sales_statement_line_mixed'),
                    $lineTypes->contains('return') => __('fields.sales_statement_line_return'),
                    default => __('fields.sales_statement_line_sale'),
                };

                return [
                    'id' => 'invoice-' . $invoiceId,
                    'group_by' => 'invoice',
                    'line_type' => $lineTypes->count() === 1 ? $lineTypes->first() : 'mixed',
                    'line_type_label' => $lineTypeLabel,
                    'date' => $group->min('date'),
                    'invoice_id' => (int) $invoiceId,
                    'invoice_no' => $first['invoice_no'],
                    'customer_name' => $first['customer_name'],
                    'product_name' => null,
                    'items_count' => $group->count(),
                    'qty' => $salesQty - $returnsQty,
                    'sales_qty' => $salesQty,
                    'returns_qty' => $returnsQty,
                    'unit_price' => null,
                    'discount' => $sales->sum('discount') - $returns->sum('discount'),
                    'tax' => $sales->sum('tax') - $returns->sum('tax'),
                    'total' => $salesTotal - $returnsTotal,
                    'gross_total' => $salesTotal,
                    'returns_total' => $returnsTotal,
                ];
            })
            ->sort($this->newestFirstSorter())
            ->values()
            ->all();
    }

    protected function newestFirstSorter(): callable
    {
        return function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));

            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcmp((string) ($b['invoice_no'] ?? ''), (string) ($a['invoice_no'] ?? ''));
        };
    }

    /** @return array<int, array<string, mixed>> */
    protected function buildSalesLines(
        Carbon $from,
        Carbon $to,
        array $customerIds,
        array $productIds,
    ): array {
        $lines = [];

        InvoiceItem::query()
            ->with(['invoice.customer', 'product'])
            ->where('cancelled', false)
            ->when($productIds !== [], fn ($q) => $q->whereIn('product_id', $productIds))
            ->whereHas('invoice', function ($q) use ($from, $to, $customerIds) {
                $this->applySalesInvoiceFilter($q, $from, $to);
                if ($customerIds !== []) {
                    $q->whereIn('customer_id', $customerIds);
                }
            })
            ->get()
            ->each(function (InvoiceItem $item) use (&$lines) {
                $qty = $this->invoiceItemOriginalQty($item);

                if ($qty <= 0) {
                    return;
                }

                $amounts = $this->invoiceItemAmounts($item, $qty);
                $invoice = $item->invoice;

                $lines[] = [
                    'id' => 'sale-' . $item->id,
                    'line_type' => 'sale',
                    'line_type_label' => __('fields.sales_statement_line_sale'),
                    'date' => $this->formatDate($invoice->locked_at ?? $invoice->created_at),
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->no,
                    'invoice_status' => $invoice->status,
                    'invoice_status_label' => $this->invoiceStatusLabel($invoice->status),
                    'customer_name' => $invoice->customer?->name,
                    'product_name' => $item->name ?: $item->product?->name,
                    'qty' => $qty,
                    'unit_price' => (float) $item->price,
                    'discount' => $amounts['discount'],
                    'tax' => $amounts['tax'],
                    'total' => $amounts['total'],
                ];
            });

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    protected function buildReturnLines(
        Carbon $from,
        Carbon $to,
        array $customerIds,
        array $productIds,
    ): array {
        $lines = [];

        SalesReturnsDetails::query()
            ->with(['invoiceItem.invoice.customer', 'invoiceItem.product'])
            ->whereBetween('created_at', [$from, $to])
            ->when($productIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem',
                fn ($inner) => $inner->whereIn('product_id', $productIds)
            ))
            ->when($customerIds !== [], fn ($q) => $q->whereHas(
                'invoiceItem.invoice',
                fn ($inner) => $inner->whereIn('customer_id', $customerIds)
            ))
            ->get()
            ->each(function (SalesReturnsDetails $detail) use (&$lines) {
                $item = $detail->invoiceItem;
                $invoice = $item?->invoice;

                if (! $item || ! $invoice) {
                    return;
                }

                $lines[] = [
                    'id' => 'return-' . $detail->id,
                    'line_type' => 'return',
                    'line_type_label' => __('fields.sales_statement_line_return'),
                    'date' => $this->formatDate($detail->created_at),
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->no,
                    'invoice_status' => $invoice->status,
                    'invoice_status_label' => $this->invoiceStatusLabel($invoice->status),
                    'customer_name' => $invoice->customer?->name,
                    'product_name' => $item->name ?: $item->product?->name,
                    'qty' => (float) $detail->qty,
                    'unit_price' => (float) $detail->price,
                    'discount' => (float) $detail->discount,
                    'tax' => (float) $detail->tax,
                    'total' => (float) $detail->total,
                ];
            });

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $salesLines
     * @param  array<int, array<string, mixed>>  $returnLines
     * @return array<string, mixed>
     */
    protected function buildStats(array $salesLines, array $returnLines): array
    {
        $salesCollection = collect($salesLines);
        $returnsCollection = collect($returnLines);

        $grossTotal = $salesCollection->sum('total');
        $returnsTotal = $returnsCollection->sum('total');

        return [
            'invoices_count' => $salesCollection->pluck('invoice_id')->unique()->count(),
            'sales_qty' => $salesCollection->sum('qty'),
            'returns_qty' => $returnsCollection->sum('qty'),
            'net_qty' => $salesCollection->sum('qty') - $returnsCollection->sum('qty'),
            'discount_total' => $salesCollection->sum('discount'),
            'tax_total' => $salesCollection->sum('tax') - $returnsCollection->sum('tax'),
            'gross_total' => $grossTotal,
            'returns_total' => $returnsTotal,
            'net_total' => $grossTotal - $returnsTotal,
        ];
    }

    protected function applySalesInvoiceFilter($query, Carbon $from, Carbon $to): void
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

    /** @return array{discount: float, tax: float, total: float} */
    protected function invoiceItemAmounts(InvoiceItem $item, float $qty): array
    {
        $originalQty = (float) ($item->getRawOriginal('qty') ?: $qty ?: 1);
        $ratio = $originalQty > 0 ? ($qty / $originalQty) : 1;

        $item->loadMissing(['invoice', 'extras']);

        $extras = 0.0;
        foreach ($item->extras as $extra) {
            $extras += (float) $extra->unit_price * $qty;
        }

        $discount = (float) $item->discount * $ratio;
        $tax = (float) $item->tax * $ratio;
        $subtotal = ((float) $item->price * $qty) + $extras - $discount;

        if ($item->invoice->prices_includes_taxes) {
            $total = $subtotal;
            $tax = 0.0;
        } else {
            $total = $subtotal + $tax;
        }

        return [
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    protected function invoiceItemOriginalQty(InvoiceItem $item): float
    {
        $raw = $item->getRawOriginal('qty');

        if ($raw !== null) {
            return (float) $raw;
        }

        return (float) ($item->getAttributes()['qty'] ?? 0);
    }

    protected function invoiceStatusLabel(?string $status): string
    {
        return match ($status) {
            'confirmed' => __('fields.invoice_status_confirmed'),
            'sale_order' => __('fields.invoice_status_sale_order'),
            'cancelled' => __('fields.invoice_status_cancelled'),
            default => $status ?? '—',
        };
    }

    protected function parseDate(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function formatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /** @return array{lines: array<int, mixed>, stats: array<string, mixed>, filters: array<string, mixed>} */
    protected function emptyReport(array $filters): array
    {
        return [
            'lines' => [],
            'stats' => [
                'invoices_count' => 0,
                'sales_qty' => 0.0,
                'returns_qty' => 0.0,
                'net_qty' => 0.0,
                'discount_total' => 0.0,
                'tax_total' => 0.0,
                'gross_total' => 0.0,
                'returns_total' => 0.0,
                'net_total' => 0.0,
                'currency' => main_currency_iso_code(),
            ],
            'filters' => $filters,
        ];
    }
}
