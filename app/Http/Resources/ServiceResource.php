<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $tax = $this->price * (collect($this->tax_profile_data['taxes'] ?? null)->sum('percent') / 100);
        return $this->filterFields([
            'id' => $this->id,
            'name' => $this->type?->name ?? '-',
            'description' => $this->description,
            'price' => number_format($this->price, currency_decimals(), '.', ''),
            'tax' => number_format($tax, currency_decimals(), '.', ''),
            'subTotal' => number_format($this->price + $tax, currency_decimals(), '.', ''),
            'serviceType' => $this->type,
            'taxProfile' => $this->taxProfile,
        ]);
    }
}
