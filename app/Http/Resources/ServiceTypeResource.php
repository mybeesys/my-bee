<?php

namespace App\Http\Resources;

class ServiceTypeResource extends BaseResource
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
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
        ]);
    }
}
