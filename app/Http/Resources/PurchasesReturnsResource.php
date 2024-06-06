<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasesReturnsResource extends BaseResource
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
            'invoiceItemId' => $this->invoice_item_id,
            'invoiceNo' => $this->invoice->no,
            'supplierId' => $this->invoice->supplier_id,
            'notes' => $this->notes,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'items' => PurchasesReturnsDetailsResource::collection($this->details),
            'user' => new UserResource($this->user),
        ]);
    }
}
