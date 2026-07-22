<?php

namespace App\Services;

use App\Models\AdditionalCost;
use App\Models\AdditionalCostType;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\OrderDiscountService;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    /**
     * @return array<string, string>
     */
    public function allowedStatusOptions(Order $order): array
    {
        if ($order->status === Order::$STATUS_CANCELLED) {
            return [];
        }

        return [
            Order::$STATUS_PACKAGING => __('fields.order_status_' . Order::$STATUS_PACKAGING),
            Order::$STATUS_DELIVERY_IN_PROGRESS => __('fields.order_status_' . Order::$STATUS_DELIVERY_IN_PROGRESS),
            Order::$STATUS_COMPLETED => __('fields.order_status_' . Order::$STATUS_COMPLETED),
            Order::$STATUS_CANCELLED => __('fields.order_status_' . Order::$STATUS_CANCELLED),
        ];
    }

    public function canChangeStatus(Order $order): bool
    {
        return $order->status !== Order::$STATUS_CANCELLED;
    }

    public function applyStatusChange(Order $order, array $data): void
    {
        DB::transaction(function () use ($order, $data) {
            $newStatus = $data['status'];

            if (array_key_exists('delivery', $data)) {
                $this->syncDeliveryFee($order, (float) $data['delivery']);
            }

            $this->syncInvoiceForStatus($order, $newStatus);

            $order->update(collect($data)->only([
                'status',
                'delivery_date',
                'canceled_date',
                'canceled_reason',
                'delivery',
            ])->all());
        });
    }

    public function confirmSalesInvoice(Order $order): void
    {
        if (! $order->invoice) {
            throw new \RuntimeException(__('fields.invoice_not_found'));
        }

        if ($order->status === Order::$STATUS_CANCELLED) {
            throw new \RuntimeException(__('fields.order_cancelled_cannot_confirm_invoice'));
        }

        if ($order->invoice->isLocked()) {
            return;
        }

        DB::transaction(function () use ($order) {
            OrderDiscountService::instance()->syncInvoiceDiscountFromOrder($order->fresh(['invoice.items']));
            $order->invoice->confirmSalesInvoice();
        });
    }

    protected function syncInvoiceForStatus(Order $order, string $newStatus): void
    {
        $invoice = $order->invoice;

        if (! $invoice) {
            return;
        }

        if ($newStatus === Order::$STATUS_CANCELLED) {
            $invoice->update([
                'status' => 'cancelled',
                'locked_by_id' => auth()->id(),
                'locked_at' => now(),
            ]);

            return;
        }

        if ($newStatus === Order::$STATUS_COMPLETED && ! $invoice->isLocked()) {
            $invoice->confirmSalesInvoice();

            return;
        }
    }

    public function syncDeliveryFee(Order $order, float $delivery): void
    {
        $invoice = $order->invoice;

        if (! $invoice) {
            return;
        }

        $invAdditionalCost = AdditionalCost::query()
            ->where('meta->type', 'delivery_fees')
            ->where('item_type', Invoice::class)
            ->where('item_id', $invoice->id)
            ->first();

        if (! $invAdditionalCost) {
            $costTypeDelivery = AdditionalCostType::firstOrCreate([
                'name' => 'توصيل/شحن',
            ], [
                'name' => 'توصيل/شحن',
                'tenant_id' => get_tenant()->id,
            ]);

            $statementEn = "Delivery fees, order no #$order->no";
            $statementAr = "رسوم توصيل الطلب:#$order->no";

            $invAdditionalCost = AdditionalCost::create([
                'tenant_id' => get_tenant()->id,
                'item_id' => $invoice->id,
                'item_type' => Invoice::class,
                'additional_cost_type_id' => $costTypeDelivery->id,
                'statement' => $statementAr . ' - ' . $statementEn,
                'cost' => 0,
                'meta' => [
                    'type' => 'delivery_fees',
                    'client' => $order->customer->name,
                    'client_id' => $order->customer_id,
                ],
            ]);
        }

        $invAdditionalCost->update([
            'cost' => $delivery,
        ]);
    }
}
