<?php

namespace App\Http\Resources;

class SupplyOrderResource extends BaseResource
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
            'no' => $this->no,
            'date' => $this->created_at->format("d-m-Y"),
            'supplier' => new SupplierResource($this->supplier),
            'items' => SupplyOrderDetailsResource::collection($this->details)
        ]);
    }
}
