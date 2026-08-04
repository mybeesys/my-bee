<?php

namespace App\Http\Resources;

class SalesReturnsResource extends BaseResource
{
    public function toArray($request): array
    {
        $returnMode = $this->isCustomerReturn() ? 'customer' : 'invoice';
        $detailsTotal = (float) $this->details->sum('total');

        return $this->filterFields([
            'id' => $this->id,
            'returnMode' => $returnMode,
            'invoiceNo' => $this->invoice?->no,
            'invoiceId' => $this->invoice_id,
            'customerId' => $this->customer_id ?? $this->invoice?->customer_id,
            'paymentTerms' => $this->payment_terms ?? 'cash',
            'refundAcc4Code' => $this->refund_acc4_code,
            'notes' => $this->notes,
            'totalExTax' => (float) $this->details->sum('price'),
            'totalTax' => (float) $this->details->sum('tax'),
            'totalDiscount' => (float) $this->details->sum('discount'),
            'totalIncTax' => $detailsTotal,
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'items' => SalesReturnsDetailsResource::collection($this->whenLoaded('details')),
            'user' => new UserResource($this->whenLoaded('user')),
        ]);
    }
}
