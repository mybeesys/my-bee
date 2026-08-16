<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends BaseResource
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
            'stateId' => $this->state_id,
            'hasAreas' => $this->when(
                isset($this->areas_count) || $this->relationLoaded('areas'),
                fn () => isset($this->areas_count)
                    ? $this->areas_count > 0
                    : $this->areas->isNotEmpty()
            ),
        ]);
    }
}
