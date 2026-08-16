<?php

namespace App\Http\Resources;

use App\Models\ItemExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListProductsForAdvancedCreationResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'taxProfileId' => $this->tax_profile_id,
            'selectVariantOptions' => ListProductsForAdvancedCreationVariantOptionsResource::collection($this->variantOptions),
            'variants' => ListProductsForAdvancedCreationVariantResource::collection($this->variants),
            'selectExtras' => ListProductsForAdvancedCreationExtrasResource::collection($this->extras),
        ];
    }
}
