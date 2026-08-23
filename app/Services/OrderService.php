<?php

namespace App\Services;

use App\Models\AdditionalCost;
use App\Models\AdditionalCostType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\ProductVariant;
use App\Services\Concerns\ResolvesInvoiceProductLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    use ResolvesInvoiceProductLines;

    /**
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        return [
            'tenant',
            'customer',
            'details.orderDetailsExtras',
            'invoice.items.extras',
            'invoice.receiptVoucher',
            'coupon',
            'state',
            'city',
            'area',
        ];
    }

    public function create(array $payload, int $tenantId, int $userId): Order
    {
        return DB::transaction(function () use ($payload, $tenantId, $userId) {
            $order = Order::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'source' => 'dashboard',
                'payment_method' => $payload['payment_method'] ?? 'cash_on_delivery',
                'delivery_type' => $payload['delivery_type'] ?? 'delivery',
                'customer_id' => $payload['customer_id'],
                'delivery_address' => $payload['delivery_address'],
                'delivery' => $payload['delivery'] ?? 0,
                'notes' => $payload['notes'] ?? null,
                'state_id' => $payload['state_id'] ?? null,
                'city_id' => $payload['city_id'] ?? null,
                'area_id' => $payload['area_id'] ?? null,
            ]);

            $this->persistDetails($order, $payload['details'], $tenantId, $userId);
            $this->createInvoice($order, $tenantId, $userId);

            return $order->fresh()->load(self::eagerLoads());
        });
    }

    public function replace(Order $order, array $payload, int $tenantId, int $userId): Order
    {
        $this->assertEditable($order);

        return DB::transaction(function () use ($order, $payload, $tenantId, $userId) {
            $header = [];

            foreach (['customer_id', 'delivery_address', 'delivery', 'notes', 'state_id', 'city_id', 'area_id', 'delivery_type', 'payment_method'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $header[$field] = $payload[$field];
                }
            }

            if ($header !== []) {
                $order->update($header);

                if ($order->invoice && array_key_exists('customer_id', $header)) {
                    $order->invoice->update(['customer_id' => $header['customer_id']]);
                }
            }

            if (array_key_exists('details', $payload)) {
                $order->details()->each(function (OrderDetail $detail) {
                    $detail->orderDetailsExtras()->delete();
                });
                $order->details()->delete();

                if ($order->invoice) {
                    $order->invoice->items()->delete();
                }

                $this->persistDetails($order, $payload['details'], $tenantId, $userId);
                $this->syncInvoiceItemsFromDetails($order, $tenantId);
            }

            if ($order->invoice) {
                app(OrderStatusService::class)->syncDeliveryFee($order->fresh(['customer']), (float) $order->delivery);
                OrderDiscountService::instance()->syncInvoiceDiscountFromOrder($order->fresh(['invoice.items']));
            }

            return $order->fresh()->load(self::eagerLoads());
        });
    }

    public function confirmInvoice(Order $order): Order
    {
        app(OrderStatusService::class)->confirmSalesInvoice($order);

        return $order->fresh()->load(self::eagerLoads());
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $completed = Order::completed()->with(['invoice.salesPayments'])->get();
        $revenue = 0.0;

        foreach ($completed as $order) {
            if ($order->invoice) {
                $revenue += (float) $order->invoice->total_paid;
            }
        }

        return [
            'all' => Order::count(),
            'new' => Order::new()->count(),
            'packaging' => Order::packaging()->count(),
            'deliveryInProgress' => Order::deliveryInProgress()->count(),
            'completed' => Order::completed()->count(),
            'cancelled' => Order::cancelled()->count(),
            'revenue' => round($revenue, currency_decimals()),
            'currency' => main_currency_iso_code(),
        ];
    }

    public function canEdit(Order $order): bool
    {
        return ! in_array($order->status, [Order::$STATUS_COMPLETED, Order::$STATUS_CANCELLED], true);
    }

    public function assertEditable(Order $order): void
    {
        if (! $this->canEdit($order)) {
            throw ValidationException::withMessages([
                'order' => __('fields.order_will_be_locked_after_this_action'),
            ]);
        }
    }

    protected function createInvoice(Order $order, int $tenantId, int $userId): Invoice
    {
        $order->refresh()->load(['details.item', 'details.orderDetailsExtras', 'customer']);

        $invoice = Invoice::create([
            'no' => generate_invoice_no(),
            'tenant_id' => $tenantId,
            'type' => 'sales',
            'status' => 'sale_order',
            'for' => 'customer',
            'customer_id' => $order->customer_id,
            'user_id' => $userId,
            'date' => now(),
            'notes' => 'sales',
        ]);

        $order->update(['invoice_id' => $invoice->id]);

        foreach ($order->details as $detail) {
            $this->insertInvoiceItem($invoice, $detail, $tenantId);
        }

        $costTypeDelivery = AdditionalCostType::firstOrCreate([
            'name' => 'توصيل/شحن',
        ], [
            'name' => 'توصيل/شحن',
            'tenant_id' => $tenantId,
        ]);

        $statementEn = "Delivery fees, order no #$order->no";
        $statementAr = "رسوم توصيل الطلب:#$order->no";

        AdditionalCost::create([
            'tenant_id' => $tenantId,
            'item_id' => $invoice->id,
            'item_type' => Invoice::class,
            'additional_cost_type_id' => $costTypeDelivery->id,
            'statement' => $statementAr . ' - ' . $statementEn,
            'cost' => $order->delivery,
            'meta' => [
                'type' => 'delivery_fees',
                'client' => $order->customer->name,
                'client_id' => $order->customer_id,
            ],
        ]);

        OrderDiscountService::instance()->syncInvoiceDiscountFromOrder($order->fresh(['invoice.items']));

        return $invoice;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     */
    protected function persistDetails(Order $order, array $details, int $tenantId, int $userId): void
    {
        $seen = [];

        foreach ($details as $index => $detail) {
            $line = $this->buildDetailLine($detail, $tenantId, $index);
            $key = $line['item_type'] . ':' . $line['item_id'];

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "details.$index" => __('fields.order_details_item_already_exists'),
                ]);
            }

            $seen[$key] = true;

            $orderDetail = OrderDetail::create([
                'tenant_id' => $tenantId,
                'order_id' => $order->id,
                'user_id' => $userId,
                'item_id' => $line['item_id'],
                'item_type' => $line['item_type'],
                'unit_price' => $line['unit_price'],
                'discount' => 0,
                'tax' => $line['tax'],
                'qty' => $line['qty'],
                'taken_qty' => 0,
                'cancelled' => 0,
                'tax_profile_data' => $line['tax_profile_data'],
            ]);

            foreach ($line['product_extras_ids'] as $productExtraId) {
                $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($productExtraId);

                $orderDetail->orderDetailsExtras()->create([
                    'tenant_id' => $tenantId,
                    'order_details_id' => $orderDetail->id,
                    'product_extra_id' => $productExtraId,
                    'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                    'display_name' => $productExtra->name,
                    'qty' => 1,
                ]);
            }
        }
    }

    protected function syncInvoiceItemsFromDetails(Order $order, int $tenantId): void
    {
        $order->refresh()->load(['details.item', 'details.orderDetailsExtras', 'invoice']);

        if (! $order->invoice) {
            $this->createInvoice($order, $tenantId, (int) auth('sanctum')->id());

            return;
        }

        foreach ($order->details as $detail) {
            $this->insertInvoiceItem($order->invoice, $detail, $tenantId);
        }
    }

    protected function insertInvoiceItem(Invoice $invoice, OrderDetail $detail, int $tenantId): void
    {
        $productId = null;
        $productVariantId = null;
        $taxProfileId = null;

        if ($detail->item instanceof Product) {
            $productId = $detail->item->id;
            $taxProfileId = $detail->item->tax_profile_id;
        }

        if ($detail->item instanceof ProductVariant) {
            $productId = $detail->item->product_id;
            $productVariantId = $detail->item->id;
            $taxProfileId = $detail->item->product->tax_profile_id;
        }

        $invoice->items()->create([
            'tenant_id' => $tenantId,
            'invoice_id' => $invoice->id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'order_details_id' => $detail->id,
            'tax_profile_id' => $taxProfileId,
            'discount' => 0,
            'qty' => $detail->qty,
            'price' => $detail->unit_price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{item_id: int, item_type: class-string, unit_price: float, tax: float, qty: int, tax_profile_data: ?array, product_extras_ids: array<int, int>}
     */
    protected function buildDetailLine(array $detail, int $tenantId, int $index): array
    {
        $errorKey = "details.$index";
        $resolved = $this->resolveProduct($detail, $errorKey);
        $product = Product::query()->with(['taxProfile.taxes', 'variants'])->findOrFail($resolved['product_id']);

        if (! empty($resolved['product_variant_id'])) {
            $item = ProductVariant::query()->findOrFail($resolved['product_variant_id']);
            $itemType = ProductVariant::class;
            $taxProduct = $product;
        } else {
            $item = $product;
            $itemType = Product::class;
            $taxProduct = $product;
        }

        $qty = (int) $detail['qty'];
        $unitPrice = (float) PricingService::instance()->getRetailPrice($item);

        if ($unitPrice <= 0) {
            throw ValidationException::withMessages([
                $errorKey => __('fields.product_variant_not_priced'),
            ]);
        }

        $available = StockService::instance()->getAvailableStock($item);

        if ($available <= 0) {
            throw ValidationException::withMessages([
                $errorKey => __('fields.no_stock_available'),
            ]);
        }

        if ($qty > $available) {
            throw ValidationException::withMessages([
                $errorKey => __('validation.max.numeric', ['attribute' => 'qty', 'max' => $available]),
            ]);
        }

        $taxProfile = $taxProduct->taxProfile;
        $taxProfile?->load('taxes');

        return [
            'item_id' => (int) $item->id,
            'item_type' => $itemType,
            'unit_price' => $unitPrice,
            'tax' => (float) PricingService::instance()->getTaxAmount($taxProduct, $unitPrice, $qty),
            'qty' => $qty,
            'tax_profile_data' => $taxProfile?->toArray(),
            'product_extras_ids' => array_map('intval', $detail['product_extras_ids'] ?? []),
        ];
    }
}
