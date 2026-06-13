<?php

namespace App\Http\Resources;

use App\Models\PriceOfferDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceOfferResource extends BaseResource
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
            'total' => number_format($this->resource->getItemsCost(false, false, false), currency_decimals(), '.', ','),
            'discount' => number_format($this->resource->details->sum('discount'), currency_decimals(), '.', ','),
            'totalAfterDiscount' => number_format($this->resource->getItemsCost(true, true, true), currency_decimals(), '.', ','),
            'tax' => number_format($this->resource->getTaxesAsAmount(), currency_decimals(), '.', ','),
            'totalWithTax' => number_format($this->resource->getItemsCost(true, true, true), currency_decimals(), '.', ','),
            'customer' => new CustomerResource($this->customer),
            'products' => PriceOfferDetailsResource::collection($this->details),
            'services' => ServiceResource::collection($this->services),
            'additionalCosts' => AdditionalCostResource::collection($this->additionalCosts),
        ]);
    }
}
