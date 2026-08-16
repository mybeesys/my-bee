<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListSupplyOrderRequest;
use App\Http\Requests\StoreSupplyOrderRequest;
use App\Http\Requests\UpdateSupplyOrderRequest;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Http\Resources\SupplyOrderResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProductVariant;
use App\Models\SupplyOrder;
use App\Models\Warehouse;
use App\Services\SupplyOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupplyOrderController extends BaseController
{
    public function __construct(
        protected SupplyOrderService $supplyOrders
    ) {
    }

    public function index(ListSupplyOrderRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = SupplyOrder::query()
            ->with(['supplier.acc4', 'supplier.state', 'supplier.city.state', 'supplier.area', 'tenant'])
            ->withCount('details')
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $query) use ($search) {
                    $query->where('no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', $request->input('supplier_id')))
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'))
            ->get();

        $payload = collect(SupplyOrderResource::collection($data)->resolve());

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    public function store(StoreSupplyOrderRequest $request)
    {
        if (subscription_resource_maxed_out('supply_orders', $this->getTenant(false)->client)) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('supply_orders', $this->getTenant(false)->client))
                ->respond();
        }

        $order = $this->supplyOrders->create(
            $request->validated(),
            $this->getTenant()->id,
            (int) auth('sanctum')->id(),
        );

        return $this->responder(__('messages.api.created'), 201, new SupplyOrderResource($order))->respond();
    }

    public function show(string $id)
    {
        $order = SupplyOrder::query()
            ->with(['supplier.acc4', 'supplier.state', 'supplier.city.state', 'supplier.area', 'details.item', 'tenant'])
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new SupplyOrderResource($order))->respond();
    }

    public function update(UpdateSupplyOrderRequest $request, string $id)
    {
        $order = SupplyOrder::query()->findOrFail($id);

        $order = $this->supplyOrders->update(
            $order,
            $request->validated(),
            $this->getTenant()->id,
            (int) auth('sanctum')->id(),
        );

        return $this->responder(__('messages.api.updated'), 200, new SupplyOrderResource($order))->respond();
    }

    public function destroy(string $id)
    {
        $order = SupplyOrder::query()->findOrFail($id);

        abort_if(! $this->canDelete($order), 403, __('messages.api.permission_denied'));

        $this->supplyOrders->delete($order);

        return $this->responder(__('messages.api.deleted'), 200, [])->respond();
    }

    /**
     * Prefill a temp purchase invoice from a supply order (web: create purchase invoice with supply_order_id).
     */
    public function startPurchaseInvoice(string $id)
    {
        $supplyOrder = SupplyOrder::query()
            ->with('details.item')
            ->findOrFail($id);

        if ($supplyOrder->details->isEmpty()) {
            return $this->errorBadRequest()
                ->message(__('fields.invoice_must_at_least_have_one_product'))
                ->respond();
        }

        if (subscription_resource_maxed_out('purchase_invoices', $this->getTenant(false)->client)) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('purchase_invoices', $this->getTenant(false)->client))
                ->respond();
        }

        $invoice = DB::transaction(function () use ($supplyOrder) {
            $invoice = Invoice::create([
                'tenant_id' => $this->getTenantId(),
                'no' => generate_invoice_no(),
                'status' => 'purchase_order',
                'type' => 'purchases',
                'payment_method' => 'cash_on_delivery',
                'for' => 'supplier',
                'date' => now(),
                'warehouse_id' => Warehouse::first()?->id,
                'supplier_id' => $supplyOrder->supplier_id,
                'user_id' => auth('sanctum')->id(),
                'discount_option' => 'per-item',
                'temp' => true,
            ]);

            foreach ($supplyOrder->details as $detail) {
                $item = $detail->item;
                $isVariant = $item instanceof ProductVariant;
                $productId = $isVariant ? $item->product_id : $item?->id;
                $variantId = $isVariant ? $item->id : null;

                if (! $productId) {
                    continue;
                }

                InvoiceItem::create([
                    'tenant_id' => $this->getTenantId(),
                    'invoice_id' => $invoice->id,
                    'user_id' => auth('sanctum')->id(),
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'name' => $item->name,
                    'qty' => $detail->qty,
                    'price' => 0,
                    'discount' => 0,
                    'tax' => 0,
                ]);
            }

            return $invoice;
        });

        $invoice->load([
            'items.product',
            'items.productVariant',
            'items.taxProfile',
            'items.purchasesReturnsDetails',
            'items.invoice',
            'items.user',
            'additionalCosts.type',
            'purchasePayments',
            'supplier',
            'user',
            'reviewedBy',
            'stocks',
            'purchasesReturns',
        ]);

        return $this->responder(__('messages.api.created'), 201, new PurchaseInvoiceResource($invoice))->respond();
    }

    /**
     * JSON prefill for POST purchases/commit — does not create a temp invoice.
     */
    public function purchasePrefill(string $id)
    {
        $supplyOrder = SupplyOrder::query()
            ->with(['supplier', 'details.item'])
            ->findOrFail($id);

        if ($supplyOrder->details->isEmpty()) {
            return $this->errorBadRequest()
                ->message(__('fields.invoice_must_at_least_have_one_product'))
                ->respond();
        }

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            $this->supplyOrders->purchasePrefill($supplyOrder)
        )->respond();
    }
}
