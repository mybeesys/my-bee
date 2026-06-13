<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxProfileResource extends BaseResource
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
            'totalPercentages' => $this->total_percentages,
            'displayName' => $this->name . " ($this->total_percentages%)",
            'taxes' => TaxResource::collection($this->taxes),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'canDelete' => true,
        ]);
    }
}
