<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $additionalCosts = [];

        foreach ($this->resource->additionalCosts as $additionalCost){
            $additionalCosts[] = [
                'statement' => $additionalCost->statement,
                'cost' => format_amount($additionalCost->cost),
            ];
        }

        return $this->filterFields([
            'id' => $this->id,
            'uid' => $this->uid,
            'type' => $this->type,
            'no' => $this->no,
            'status' => $this->status,
            'paymentMethod' => $this->payment_method,
            'transactionRef' => $this->transaction_ref,
            'date' => $this->date->format('d-m-Y'),
            'customer' => new CustomerResource($this->customer),
            'supplier' => new SupplierResource($this->supplier),
            'tax' => format_amount($this->resource->getTaxesAsAmount()),
            'discount' => format_amount($this->resource->items->sum('discount')),
            'services' => ServiceResource::collection($this->services),
            'additionalCosts' => $additionalCosts,
            'total' => format_amount($this->resource->getItemsCost(true, false, false)),
            'TotalAfterDiscount' => format_amount($this->resource->getItemsCost(true, true, false)),
            'TotalAfterTaxes' => format_amount($this->resource->getItemsCost(true, true, true)),
            'TotalWrittenAr' => numbers_to_words($this->resource->getItemsCost(true, true, true), 'ar'),
            'TotalWrittenEn' => numbers_to_words($this->resource->getItemsCost(true, true, true), 'en'),
            'items' => InvoiceItemResource::collection($this->items),
        ]);
    }
}
