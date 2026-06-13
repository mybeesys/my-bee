<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends BaseResource
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
            'taxProfileId' => $this->tax_profile_id,
            'description' => $this->description,
            'percent' => $this->percent,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
            'canDelete' => true,
        ]);
    }
}
