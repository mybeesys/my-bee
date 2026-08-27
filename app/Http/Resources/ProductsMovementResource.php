<?php

namespace App\Http\Resources;

use App\Services\ProductMovementBalanceService;
use Carbon\Carbon;

class ProductsMovementResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $movementType = (string) ($this->movement_type ?? $this->invoice?->type ?? '');
        $name = $this->resolveName();
        $entity = $this->resolveEntityName();
        $entityType = $this->resolveEntityType();
        $entityId = $this->resolveEntityId();
        $invoiceNo = (string) ($this->invoice_no ?? $this->invoice?->no ?? '');
        $createdAt = $this->formatCreatedAt();
        $qty = (float) ($this->getAttributes()['qty'] ?? $this->qty ?? 0);
        $balance = $this->movement_key
            ? app(ProductMovementBalanceService::class)->balanceAfterMovement($this->resource)
            : app(ProductMovementBalanceService::class)->balanceAfter($this->resource);

        $typeFormatted = match ($movementType) {
            'purchases' => __('fields.products_movements_type_purchases'),
            'sales' => __('fields.products_movements_type_sales'),
            'sales_return' => __('fields.products_movements_type_sales_return'),
            'purchase_return' => __('fields.products_movements_type_purchase_return'),
            default => $movementType !== '' ? $movementType : '',
        };

        // camelCase = primary (matches rest of mobile API).
        // snake_case kept for ProductMovementModel.fromJson compatibility.
        return $this->filterFields([
            'id' => (int) $this->id,
            'name' => $name,
            'type' => $movementType,
            'typeFormatted' => $typeFormatted,
            'type_formatted' => $typeFormatted,
            'entity' => $entity,
            'entityType' => $entityType,
            'entity_type' => $entityType,
            'entityId' => $entityId,
            'entity_id' => $entityId,
            'invoiceNo' => $invoiceNo,
            'invoice_no' => $invoiceNo,
            'invoiceId' => $this->invoice_id !== null ? (int) $this->invoice_id : null,
            'movementKey' => (string) ($this->movement_key ?? ''),
            'movement_key' => (string) ($this->movement_key ?? ''),
            'qty' => $qty,
            'currentQtyMovementBalance' => (float) $balance,
            'current_qty_movement_balance' => (float) $balance,
            'createdAt' => $createdAt,
            'created_at' => $createdAt,
        ]);
    }

    protected function resolveName(): string
    {
        $name = trim((string) ($this->name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim((string) (
            $this->productVariant?->name
            ?? $this->product?->name
            ?? ''
        ));
    }

    protected function resolveEntityName(): string
    {
        $entity = trim((string) ($this->entity_name ?? ''));

        if ($entity !== '' && $entity !== '-') {
            return $entity;
        }

        if ($this->invoice?->customer_id) {
            return trim((string) ($this->invoice->customer?->name ?? '')) ?: '-';
        }

        if ($this->invoice?->supplier_id) {
            return trim((string) ($this->invoice->supplier?->name ?? '')) ?: '-';
        }

        return '-';
    }

    protected function resolveEntityType(): string
    {
        if ($this->customer_id || $this->invoice?->customer_id) {
            return 'customer';
        }

        if ($this->supplier_id || $this->invoice?->supplier_id) {
            return 'supplier';
        }

        return '';
    }

    protected function resolveEntityId(): ?int
    {
        $id = $this->customer_id
            ?: $this->supplier_id
            ?: ($this->invoice?->customer_id ?: $this->invoice?->supplier_id);

        return $id !== null ? (int) $id : null;
    }

    protected function formatCreatedAt(): string
    {
        $value = $this->created_at;

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        if (filled($value)) {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        }

        return '';
    }
}
