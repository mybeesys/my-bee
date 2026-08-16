<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdditionalCostResource extends BaseResource
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
            'additionalCostTypeId' => $this->additional_cost_type_id,
            'taxProfileId' => $this->tax_profile_id,
            'name' => $this->type?->name ?? '-',
            'description' => $this->statement,
            'cost' => number_format($this->cost, currency_decimals(), '.', ''),
        ]);
    }
}
