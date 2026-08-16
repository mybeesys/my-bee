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
            'paymentTerms' => $this->payment_terms ?? 'credit',
            'paymentStatus' => $this->resource->getPaymentStatus("en"),
            'paymentStatusFormatted' => $this->payment_status,
            'settlementStatusKey' => $this->settlement_status_key,
            'settlementStatus' => $this->payment_status,
            'paymentMethod' => $this->payment_method,
            'transactionRef' => $this->transaction_ref,
            'discountOption' => $this->discount_option,
            'discountMethod' => $this->discount_method,
            'discountAmount' => $this->discount_amount ? number_format($this->discount_amount, currency_decimals(), '.', '') : $this->discount_amount,
            'discountPercent' => $this->discount_percent ? number_format($this->discount_percent, currency_decimals(), '.', '') : $this->discount_percent,
            'pricesIncludesTaxes' => (bool) $this->prices_includes_taxes,
            'date' => $this->date->format('F j, Y, g:i a'),
            'isPaid' => $this->paid,
            'lockedAt' => $this->locked_at
                ? \Carbon\Carbon::parse($this->locked_at)->format('Y-m-d H:i:s')
                : null,
            'supplierId' => $this->supplier_id,
            'warehouseId' => $this->warehouse_id,
            'totalAmount' => number_format($this->getItemsCost(true, true, true), currency_decimals(), '.', ''),
            'totalAmountWritten' => numbers_to_words($this->getItemsCost(true, true, true)),
            'paidAmount' => number_format($this->total_paid, currency_decimals(), '.', ''),
            'paidAmountPercent' => number_format($this->total_paid_percent, currency_decimals(), '.', ''),
            'unpaidAmount' => number_format($this->total_unpaid, currency_decimals(), '.', ''),
            'canUpdateStatus' => $this->status == "purchase_order",
            'canEdit' => $this->locked_at === null && $this->status !== 'cancelled',
            'hasPurchaseReturn' => (int) ($this->purchases_returns_count ?? $this->purchasesReturns?->count() ?? 0) > 0,
            'purchasesReturnsCount' => (int) ($this->purchases_returns_count ?? $this->purchasesReturns?->count() ?? 0),
            'paymentVoucherId' => $this->relationLoaded('paymentVoucher')
                ? $this->paymentVoucher?->id
                : null,
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
