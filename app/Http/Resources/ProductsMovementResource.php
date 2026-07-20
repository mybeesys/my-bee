<?php

namespace App\Http\Resources;

use App\Services\ProductMovementBalanceService;
use Illuminate\Http\Request;

class ProductsMovementResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $movementType = $this->movement_type ?? $this->invoice?->type;

        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->name,
            'type' => $movementType,
            'type_formatted' => match ($movementType) {
                'purchases' => __('fields.products_movements_type_purchases'),
                'sales' => __('fields.products_movements_type_sales'),
                'sales_return' => __('fields.products_movements_type_sales_return'),
                'purchase_return' => __('fields.products_movements_type_purchase_return'),
                default => $movementType,
            },
            'entity' => $this->entity_name
                ?? ($this->invoice?->customer_id
                    ? ($this->invoice->customer?->name ?? '-')
                    : ($this->invoice->supplier?->name ?? '-')),
            'entity_type' => $this->customer_id || $this->invoice?->customer_id ? 'customer' : 'supplier',
            'entity_id' => $this->customer_id
                ?: $this->supplier_id
                ?: ($this->invoice?->customer_id ?: $this->invoice?->supplier_id),
            'invoice_no' => $this->invoice_no ?? $this->invoice?->no,
            'qty' => $this->qty,
            'current_qty_movement_balance' => $this->movement_key
                ? app(ProductMovementBalanceService::class)->balanceAfterMovement($this->resource)
                : app(ProductMovementBalanceService::class)->balanceAfter($this->resource),
            'created_at' => $this->created_at?->format('F j, Y, g:i a'),
        ]);
    }
}
