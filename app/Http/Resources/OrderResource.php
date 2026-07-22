<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Services\OrderDiscountService;
use Illuminate\Support\Facades\DB;

class OrderResource extends BaseResource
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
            'invoiceId' => $this->invoice?->getKey(),
            'invoiceNo' => $this->invoice?->no,
            'invoiceReceiptVoucherId' => $this->resolveInvoiceReceiptVoucherId(),
            'invoiceUID' => $this->invoice?->uid,
            'status' => $this->status,
            'statusName' => __("fields.order_status_$this->status"),
            'paymentStatus' => $this->invoice?->payment_status,
            'paymentMethod' => $this->payment_method,
            'discount' => $this->invoice
                ? number_format(OrderDiscountService::instance()->orderDiscountAmount($this->resource), currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'delivery' => number_format($this->delivery, currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol(),
            'extras' => $this->invoice
                ? number_format($this->invoice->extras_total, currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'tax' => $this->invoice
                ? number_format($this->invoice->getTaxesAsAmount(), currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'total' => $this->invoice
                ? number_format(OrderDiscountService::instance()->orderGrandTotal($this->resource), currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'orderDate' => $this->created_at->format('F j, Y, g:i a'),
            'deliveryDate' => $this->delivery_date?->format('F j, Y, g:i a'),
            'cancelledDate' => $this->canceled_date?->format('F j, Y, g:i a'),
            'customer' => $this->customer ? new CustomerResource($this->customer) : null,
            'details' => $this->invoice
                ? OrderItemResource::collection($this->invoice->items)
                : [],
            'coupon' => $this->coupon ? new CouponResource($this->coupon) : null,
        ]);
    }

    protected function resolveInvoiceReceiptVoucherId(): ?int
    {
        if (! $this->invoice) {
            return null;
        }

        $invoiceId = (int) $this->invoice->getKey();

        if ($this->invoice->relationLoaded('receiptVoucher') && $this->invoice->receiptVoucher) {
            return (int) $this->invoice->receiptVoucher->getKey();
        }

        $directId = DB::table('receipt_vouchers')
            ->where('invoice_id', $invoiceId)
            ->value('id');

        if ($directId) {
            return (int) $directId;
        }

        $viaPayment = DB::table('receipt_voucher_payments')
            ->where('model_type', Invoice::class)
            ->where('model_id', $invoiceId)
            ->value('receipt_voucher_id');

        return $viaPayment ? (int) $viaPayment : null;
    }
}
