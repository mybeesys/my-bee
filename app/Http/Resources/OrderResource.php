<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'invoiceUID' => $this->invoice->uid,
            'status' => $this->status,
            'statusName' => __("fields.order_status_$this->status"),
            'paymentStatus' => $this->invoice?->payment_status,
            'paymentMethod' => $this->payment_method,
            'discount' => number_format($this->invoice->getDiscountInAmount(), currency_decimals(), '.', ',') . " ". main_currency_native_symbol(),
            'delivery' => number_format($this->delivery, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'extras' => number_format($this->invoice->extras_total, currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'tax' => number_format($this->invoice->getTaxesAsAmount(), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'total' => number_format($this->invoice->getItemsCost(true, true, true), currency_decimals(), '.', ',') . " " . main_currency_native_symbol(),
            'orderDate' => $this->created_at->format('F j, Y, g:i a'),
            'deliveryDate' => $this->delivery_date?->format('F j, Y, g:i a'),
            'cancelledDate' => $this->cancelled_date?->format('F j, Y, g:i a'),
            'customer' => new CustomerResource($this->customer),
//            'details' => OrderDetailsResource::collection($this->details),
            'details' => OrderItemResource::collection($this->invoice->items),
//            'payments' => OrderPaymentResource::collection($this->invoice->salesPayments),
            'coupon' => new CouponResource($this->coupon),
        ]);
    }
}
