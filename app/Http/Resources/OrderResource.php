<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\OrderDiscountService;
use App\Services\OrderService;
use App\Services\OrderStatusService;
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
        $invoice = $this->invoice;
        $invoiceFinal = $this->isFinalCustomerInvoice($invoice);
        $grandTotal = $invoice
            ? OrderDiscountService::instance()->orderGrandTotal($this->resource)
            : null;

        return $this->filterFields([
            'id' => $this->id,
            'no' => $this->no,
            'source' => $this->source,
            'invoiceId' => $invoice?->getKey(),
            'invoiceNo' => $invoice?->no,
            'invoiceStatus' => $invoice?->status,
            'invoiceFinal' => $invoiceFinal,
            'invoiceReceiptVoucherId' => $this->resolveInvoiceReceiptVoucherId(),
            'invoiceUID' => $invoice?->uid,
            // Review invoice: GET /api/v1/tenant/shop/{invoiceShowPath} — do not list all sales invoices
            'invoiceShowPath' => $invoice ? 'sales/' . $invoice->getKey() : null,
            'status' => $this->status,
            'statusName' => __("fields.order_status_$this->status"),
            'paymentStatus' => $invoice?->payment_status,
            'paymentMethod' => $this->payment_method,
            'deliveryType' => $this->delivery_type,
            'deliveryAddress' => $this->delivery_address,
            'stateName' => $this->state?->name,
            'cityName' => $this->city?->name,
            'areaName' => $this->area?->name,
            'fullAddress' => $this->resolveFullDeliveryAddress(),
            'notes' => $this->notes,
            'canceledReason' => $this->canceled_reason,
            'isPaid' => (bool) $invoice?->paid,
            'canEdit' => app(OrderService::class)->canEdit($this->resource),
            'discount' => $invoice
                ? number_format(OrderDiscountService::instance()->orderDiscountAmount($this->resource), currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'discountAmount' => round((float) OrderDiscountService::instance()->orderDiscountAmount($this->resource), currency_decimals()),
            'delivery' => number_format($this->delivery, currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol(),
            'deliveryAmount' => round((float) $this->delivery, currency_decimals()),
            'extras' => $invoice
                ? number_format($invoice->extras_total, currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'tax' => $invoice
                ? number_format($invoice->getTaxesAsAmount(), currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'total' => $grandTotal !== null
                ? number_format($grandTotal, currency_decimals(), '.', ',') . ' ' . main_currency_native_symbol()
                : null,
            'totalAmount' => $grandTotal !== null ? round((float) $grandTotal, currency_decimals()) : null,
            'currency' => main_currency_iso_code(),
            'shareUrl' => $invoiceFinal ? $invoice?->url : null,
            'pdfUrl' => $invoiceFinal ? $invoice?->pdf_url : null,
            'orderDate' => $this->created_at->format('F j, Y, g:i a'),
            'orderDateFormatted' => $this->created_at?->format('Y-m-d H:i:s'),
            'deliveryDate' => $this->delivery_date?->format('F j, Y, g:i a'),
            'deliveryDateFormatted' => $this->delivery_date?->format('Y-m-d H:i:s'),
            'cancelledDate' => $this->canceled_date?->format('F j, Y, g:i a'),
            'cancelledDateFormatted' => $this->canceled_date?->format('Y-m-d H:i:s'),
            'customer' => $this->customer ? new CustomerResource($this->customer) : null,
            'details' => $invoice
                ? OrderItemResource::collection($invoice->items)
                : [],
            'coupon' => $this->coupon ? new CouponResource($this->coupon) : null,
            'actions' => $this->resolveActions(),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    protected function resolveActions(): array
    {
        $invoice = $this->invoice;
        $statusService = app(OrderStatusService::class);
        $canReviewInvoice = filled($this->invoice_id)
            && $invoice?->isEditable()
            && $this->status !== Order::$STATUS_CANCELLED;

        return [
            'canChangeStatus' => $statusService->canChangeStatus($this->resource),
            'canReviewInvoice' => $canReviewInvoice,
            'canConfirmInvoice' => $canReviewInvoice,
            'canCompletePayment' => $invoice && ! $invoice->paid,
            'canEdit' => app(OrderService::class)->canEdit($this->resource),
            'canShare' => $this->isFinalCustomerInvoice($invoice),
        ];
    }

    protected function isFinalCustomerInvoice(?Invoice $invoice): bool
    {
        if (! $invoice) {
            return false;
        }

        return $invoice->type === Invoice::$TYPE_SALES
            && $invoice->status === 'confirmed'
            && ! $invoice->temp
            && filled($invoice->uid);
    }

    protected function resolveFullDeliveryAddress(): ?string
    {
        $parts = array_values(array_filter([
            $this->state?->name,
            $this->city?->name,
            $this->area?->name,
            $this->delivery_address,
        ], fn ($part) => filled($part)));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
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
