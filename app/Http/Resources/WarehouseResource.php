<?php

namespace App\Http\Resources;


class WarehouseResource extends BaseResource
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
            'address' => $this->address,
            'phone' => $this->phone,
            'description' => $this->description,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at->format('F j, Y, g:i a'),
            'canDelete' => true,
            'inventoryCount' => $this->stocks->count(),
            'inventory' => [],
        ]);
    }
}
