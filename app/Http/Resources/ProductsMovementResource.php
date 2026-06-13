<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductsMovementResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->name,

            'type' => $this->invoice->type,

            'type_formatted' => $this->invoice->type == "purchases" ?
                __('fields.products_movements_type_purchases')
                : __('fields.products_movements_type_sales'),

            'entity' => $this->invoice->customer_id ? $this->invoice->customer->name : $this->invoice->supplier->name,
            'entity_type' => $this->invoice->customer_id ? "customer" : "supplier",
            'entity_id' => $this->invoice->customer_id ?: $this->invoice->supplier_id,
            'invoice_no' => $this->invoice->no,
            'qty' => $this->qty,
            'current_qty_movement_balance' => $this->current_qty_movement_balance,
            'created_at' => $this->created_at->format('F j, Y, g:i a'),
        ]);
    }
}
