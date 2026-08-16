<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StateResource extends BaseResource
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
            'countryId' => $this->country_id,
            'hasCities' => $this->when(
                isset($this->cities_count) || $this->relationLoaded('cities'),
                fn () => isset($this->cities_count)
                    ? $this->cities_count > 0
                    : $this->cities->isNotEmpty()
            ),
        ]);
    }
}
