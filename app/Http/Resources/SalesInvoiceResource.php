<?php

namespace App\Http\Resources;

class SalesInvoiceResource extends BaseResource
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
            'customerId' => $this->customer_id,
            'orderId' => $this->relationLoaded('order') ? $this->order?->id : null,
            'orderNo' => $this->relationLoaded('order') ? $this->order?->no : null,
            'shareUrl' => $this->uid && ! $this->temp ? $this->url : null,
            'pdfUrl' => $this->uid && ! $this->temp ? $this->pdf_url : null,
            'totalAmount' => number_format($this->getItemsCost(true, true, true), currency_decimals(), '.', ''),
            'totalAmountWritten' => numbers_to_words($this->getItemsCost(true, true, true)),
            'paidAmount' => number_format($this->total_paid, currency_decimals(), '.', ''),
            'paidAmountPercent' => number_format($this->total_paid_percent, currency_decimals(), '.', ''),
            'unpaidAmount' => number_format($this->total_unpaid, currency_decimals(), '.', ''),
            'servicesTotal' => number_format($this->getServicesCost(true), currency_decimals(), '.', ''),
            'additionalCostsTotal' => number_format($this->getAdditionalCosts(true), currency_decimals(), '.', ''),
            'canUpdateStatus' => $this->resource->isEditable(),
            'canEdit' => $this->resource->isEditable(),
            'hasSalesReturn' => (int) ($this->sales_returns_count ?? $this->salesReturns?->count() ?? 0) > 0,
            'salesReturnsCount' => (int) ($this->sales_returns_count ?? $this->salesReturns?->count() ?? 0),
            'salesReturnId' => $this->relationLoaded('salesReturns')
                ? $this->salesReturns->first()?->id
                : null,
            'receiptVoucherId' => $this->relationLoaded('receiptVoucher')
                ? $this->receiptVoucher?->id
                : null,
            'actions' => [
                'canShare' => filled($this->uid) && ! $this->temp,
                'canSalesReturn' => $this->status === 'confirmed' && ! $this->temp,
                'canCompletePayment' => ! $this->paid && $this->status === 'confirmed' && ! $this->temp,
                'canCreditPayment' => ($this->payment_terms ?? 'credit') === 'credit'
                    && $this->status === 'confirmed'
                    && ! $this->temp
                    && ! $this->paid,
                'canEdit' => $this->resource->isEditable(),
            ],
            'footerTotals' => [
                'total' => number_format($this->getItemsCost(true, false, false), currency_decimals(), '.', ''),
                'discount' => number_format($this->getDiscountInAmount(), currency_decimals(), '.', ''),
                'tax' => format_amount($this->getTaxesAsAmount()),
                'TotalAfterTaxes' => format_amount($this->resource->getItemsCost(true, true, true)),
                'TotalAfterTaxesWritten' => numbers_to_words($this->resource->getItemsCost(true, true, true)),
            ],
            'user' => $this->user ? new UserResource($this->user) : null,
            'customer' => $this->customer ? new CustomerResource($this->customer) : null,
            'reviewedBy' => $this->reviewedBy ? new UserResource($this->reviewedBy) : null,
            'items' => SalesInvoiceItemResource::collection($this->items),
            'services' => ServiceResource::collection($this->services),
            'additionalCosts' => AdditionalCostResource::collection($this->additionalCosts),
            'createdAt' => $this->created_at->format('F j, Y, g:i a'),
            'updatedAt' => $this->updated_at ? $this->updated_at->format('F j, Y, g:i a') : null,
        ]);
    }
}
