<?php

namespace App\Http\Resources;

use App\Models\Order;

class CustomerOrderListResource extends BaseResource
{
    /**
     * Compact order row matching the customer view orders tab.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return $this->filterFields([
            'id' => $order->id,
            'no' => $order->no,
            'invoiceId' => $order->invoice?->id,
            'invoiceNo' => $order->invoice?->no,
            'status' => $order->status,
            'statusName' => __('fields.order_status_' . $order->status),
            'paymentStatus' => $order->invoice?->payment_status,
            'subTotal' => round((float) $order->sub_total, 2),
            'discount' => round((float) $order->discount, 2),
            'delivery' => round((float) $order->delivery, 2),
            'total' => round((float) $order->total, 2),
            'currency' => main_currency_iso_code(),
            'couponCode' => $order->coupon_data['code'] ?? null,
            'deliveryType' => $order->delivery_type,
            'deliveryAddress' => $order->delivery_address,
            'orderDate' => $order->created_at?->format('Y-m-d H:i:s'),
            'deliveryDate' => $order->delivery_date?->format('Y-m-d H:i:s'),
        ]);
    }
}
