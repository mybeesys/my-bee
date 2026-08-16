<?php

namespace App\Http\Resources;

class SupplyOrderResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return $this->filterFields([
            'id' => $this->id,
            'no' => $this->no,
            'description' => $this->description,
            'supplierId' => $this->supplier_id,
            'date' => $this->created_at?->format('d-m-Y'),
            'createdAt' => $this->created_at?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i:s'),
            'shareUrl' => $this->url,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'items' => $this->whenLoaded('details', fn () => SupplyOrderDetailsResource::collection($this->details)),
        ]);
    }
}
