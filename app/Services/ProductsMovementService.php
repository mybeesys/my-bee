<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\ProductMovementLine;
use App\Models\PurchasesReturnsDetails;
use App\Models\SalesReturnsDetails;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductsMovementService
{
    /** @return array<int, array<string, mixed>> */
    public function build(array $filters = []): array
    {
        $type = $filters['type'] ?? null;

        $lines = collect();

        if ($this->includesInvoiceItems($type)) {
            $lines = $lines->merge($this->invoiceItemLines($filters, $type));
        }

        if ($this->includesSalesReturns($type)) {
            $lines = $lines->merge($this->salesReturnLines($filters));
        }

        if ($this->includesPurchaseReturns($type)) {
            $lines = $lines->merge($this->purchaseReturnLines($filters));
        }

        return $lines
            ->sortByDesc(fn (array $line) => Carbon::parse($line['created_at'])->timestamp)
            ->values()
            ->all();
    }

    protected function includesInvoiceItems(?string $type): bool
    {
        return $type === null || in_array($type, ['purchases', 'sales'], true);
    }

    protected function includesSalesReturns(?string $type): bool
    {
        return $type === null || $type === 'sales_return';
    }

    protected function includesPurchaseReturns(?string $type): bool
    {
        return $type === null || $type === 'purchase_return';
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function invoiceItemLines(array $filters, ?string $type): Collection
    {
        return InvoiceItem::query()
            ->with(['invoice.customer', 'invoice.supplier', 'product', 'productVariant'])
            ->where('cancelled', false)
            ->whereHas('invoice', function ($query) use ($filters, $type) {
                if ($type === 'purchases') {
                    $query->where('type', 'purchases');
                } elseif ($type === 'sales') {
                    $query->where('type', 'sales');
                }

                $query->when(
                    $filters['customers'] ?? null,
                    fn ($inner) => $inner->whereIn('customer_id', (array) $filters['customers'])
                )
                    ->when(
                        $filters['suppliers'] ?? null,
                        fn ($inner) => $inner->whereIn('supplier_id', (array) $filters['suppliers'])
                    )
                    ->when(
                        $filters['invoices'] ?? null,
                        fn ($inner) => $inner->whereIn('id', (array) $filters['invoices'])
                    );
            })
            ->when(
                $filters['products'] ?? null,
                fn ($query) => $query->whereIn('product_id', (array) $filters['products'])
            )
            ->when(
                $filters['created_from'] ?? null,
                fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from'])
            )
            ->when(
                $filters['created_until'] ?? null,
                fn ($query) => $query->whereDate('created_at', '<=', $filters['created_until'])
            )
            ->get()
            ->map(function (InvoiceItem $item): array {
                $invoice = $item->invoice;
                $movementType = $invoice->type === 'purchases' ? 'purchases' : 'sales';

                return [
                    'id' => 'invoice-item-' . $item->id,
                    'movement_key' => 'invoice-item-' . $item->id,
                    'movement_type' => $movementType,
                    'name' => $this->resolveItemName($item),
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'entity_name' => $invoice->customer_id
                        ? ($invoice->customer?->name ?? '-')
                        : ($invoice->supplier?->name ?? '-'),
                    'customer_id' => $invoice->customer_id,
                    'supplier_id' => $invoice->supplier_id,
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->no,
                    'invoice_type' => $invoice->type,
                    'qty' => (float) ($item->getRawOriginal('qty') ?? $item->qty),
                    'discount' => (float) $item->discount,
                    'tax' => (float) $item->tax,
                    'price' => (float) $item->price,
                    'sub_total' => (float) $item->sub_total,
                    'created_at' => $item->created_at,
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function salesReturnLines(array $filters): Collection
    {
        return SalesReturnsDetails::query()
            ->with(['invoiceItem.invoice.customer', 'invoiceItem.product', 'invoiceItem.productVariant'])
            ->when(
                $filters['created_from'] ?? null,
                fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from'])
            )
            ->when(
                $filters['created_until'] ?? null,
                fn ($query) => $query->whereDate('created_at', '<=', $filters['created_until'])
            )
            ->when(
                $filters['customers'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem.invoice',
                    fn ($inner) => $inner->whereIn('customer_id', (array) $filters['customers'])
                )
            )
            ->when(
                $filters['invoices'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem.invoice',
                    fn ($inner) => $inner->whereIn('id', (array) $filters['invoices'])
                )
            )
            ->when(
                $filters['products'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem',
                    fn ($inner) => $inner->whereIn('product_id', (array) $filters['products'])
                )
            )
            ->get()
            ->map(function (SalesReturnsDetails $detail): ?array {
                $item = $detail->invoiceItem;
                $invoice = $item?->invoice;

                if (! $item || ! $invoice) {
                    return null;
                }

                return [
                    'id' => 'sales-return-' . $detail->id,
                    'movement_key' => 'sales-return-' . $detail->id,
                    'movement_type' => 'sales_return',
                    'name' => $this->resolveItemName($item),
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'entity_name' => $invoice->customer?->name ?? '-',
                    'customer_id' => $invoice->customer_id,
                    'supplier_id' => null,
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->no,
                    'invoice_type' => 'sales',
                    'qty' => (float) $detail->qty,
                    'discount' => (float) $detail->discount,
                    'tax' => (float) $detail->tax,
                    'price' => (float) $detail->price,
                    'sub_total' => (float) $detail->total,
                    'created_at' => $detail->created_at,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    protected function purchaseReturnLines(array $filters): Collection
    {
        return PurchasesReturnsDetails::query()
            ->with(['invoiceItem.invoice.supplier', 'invoiceItem.product', 'invoiceItem.productVariant'])
            ->when(
                $filters['created_from'] ?? null,
                fn ($query) => $query->whereDate('created_at', '>=', $filters['created_from'])
            )
            ->when(
                $filters['created_until'] ?? null,
                fn ($query) => $query->whereDate('created_at', '<=', $filters['created_until'])
            )
            ->when(
                $filters['suppliers'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem.invoice',
                    fn ($inner) => $inner->whereIn('supplier_id', (array) $filters['suppliers'])
                )
            )
            ->when(
                $filters['invoices'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem.invoice',
                    fn ($inner) => $inner->whereIn('id', (array) $filters['invoices'])
                )
            )
            ->when(
                $filters['products'] ?? null,
                fn ($query) => $query->whereHas(
                    'invoiceItem',
                    fn ($inner) => $inner->whereIn('product_id', (array) $filters['products'])
                )
            )
            ->get()
            ->map(function (PurchasesReturnsDetails $detail): ?array {
                $item = $detail->invoiceItem;
                $invoice = $item?->invoice;

                if (! $item || ! $invoice) {
                    return null;
                }

                return [
                    'id' => 'purchase-return-' . $detail->id,
                    'movement_key' => 'purchase-return-' . $detail->id,
                    'movement_type' => 'purchase_return',
                    'name' => $this->resolveItemName($item),
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'entity_name' => $invoice->supplier?->name ?? '-',
                    'customer_id' => null,
                    'supplier_id' => $invoice->supplier_id,
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->no,
                    'invoice_type' => 'purchases',
                    'qty' => (float) $detail->qty,
                    'discount' => (float) $detail->discount,
                    'tax' => (float) $detail->tax,
                    'price' => (float) $detail->price,
                    'sub_total' => (float) $detail->total,
                    'created_at' => $detail->created_at,
                ];
            })
            ->filter()
            ->values();
    }

    protected function resolveItemName(InvoiceItem $item): string
    {
        $name = trim((string) ($item->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) (
            $item->productVariant?->name
            ?? $item->product?->name
            ?? ''
        ));
    }

    public function toRecords(array $lines): Collection
    {
        return collect($lines)->map(function (array $line): ProductMovementLine {
            $record = new ProductMovementLine;
            $record->forceFill($line);
            $record->setAttribute($record->getKeyName(), (string) $line['id']);
            $record->exists = true;

            return $record;
        });
    }
}
