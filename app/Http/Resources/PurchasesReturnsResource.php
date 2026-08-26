<?php

namespace App\Http\Resources;

class PurchasesReturnsResource extends BaseResource
{
    public function toArray($request): array
    {
        $returnMode = $this->isSupplierReturn() ? 'supplier' : 'invoice';
        $supplier = $this->resolveSupplier();
        $detailsTotal = (float) $this->details->sum('total');

        return $this->filterFields([
            'id' => $this->id,
            'returnMode' => $returnMode,
            'invoiceNo' => $this->invoice?->no,
            'invoiceId' => $this->invoice_id,
            'supplierId' => $this->supplier_id ?? $this->invoice?->supplier_id,
            'supplierName' => $supplier?->name,
            'paymentTerms' => $this->payment_terms ?? 'cash',
            'refundAcc4Code' => $this->refund_acc4_code,
            'notes' => $this->notes,
            'totalExTax' => (float) $this->details->sum('price'),
            'totalTax' => (float) $this->details->sum('tax'),
            'totalDiscount' => (float) $this->details->sum('discount'),
            'totalIncTax' => $detailsTotal,
            'createdAt' => optional($this->created_at)->format('F j, Y, g:i a'),
            'items' => PurchasesReturnsDetailsResource::collection($this->whenLoaded('details')),
            'user' => new UserResource($this->whenLoaded('user')),
        ]);
    }
}
