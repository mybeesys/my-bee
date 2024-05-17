<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceResource extends BaseResource
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
            'uid' => $this->uid,
            'status' => $this->status,
            'paymentStatus' => $this->resource->getPaymentStatus("en"),
            'paymentStatusFormatted' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'transactionRef' => $this->transaction_ref,
            'discountOption' => $this->discount_option,
            'discountMethod' => $this->discount_method,
            'discountAmount' => $this->discount_amount ? number_format($this->discount_amount, currency_decimals(), '.', '') : $this->discount_amount,
            'discountPercent' => $this->discount_percent ? number_format($this->discount_percent, currency_decimals(), '.', '') : $this->discount_percent,
            'date' => $this->date->format('F j, Y, g:i a'),
            'isPaid' => $this->paid,
            'totalAmount' => number_format($this->getItemsCost(true, true, true), currency_decimals(), '.', ''),
            'totalAmountWritten' => numbers_to_words($this->getItemsCost(true, true, true)),
            'paidAmount' => number_format($this->total_paid, currency_decimals(), '.', ''),
            'paidAmountPercent' => number_format($this->total_paid_percent, currency_decimals(), '.', ''),
            'unpaidAmount' => number_format($this->total_unpaid, currency_decimals(), '.', ''),
            'canUpdateStatus' => $this->status == "purchase_order",
            'footerTotals' => [
                'total' => number_format($this->getItemsCost(true, false, false), currency_decimals(), '.', ''),
                'discount' => number_format($this->getDiscountInAmount(), currency_decimals(), '.', ''),
                'tax' => format_amount($this->getTaxesAsAmount()),
                'TotalAfterTaxes' => format_amount($this->resource->getItemsCost(true, true, true)),
                'TotalAfterTaxesWritten' => numbers_to_words($this->resource->getItemsCost(true, true, true)),
            ],
            'warehouse' => new WarehouseResource($this->warehouse),
            'user' => new UserResource($this->user),
            'supplier' => new SupplierResource($this->supplier),
            'reviewedBy' => new UserResource($this->reviewedBy),
            'items' => PurchaseInvoiceItemResource::collection($this->items),
            'additionalCosts' => AdditionalCostResource::collection($this->additionalCosts),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
        ]);
    }
}
