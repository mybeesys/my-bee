<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\AddAdditionalCostForPurchaseInvoiceRequest;
use App\Http\Requests\AddProductForPurchaseInvoiceRequest;
use App\Http\Requests\ApplyOverallDiscountForPurchaseInvoiceRequest;
use App\Http\Requests\DeleteAdditionalCostForPurchaseInvoiceRequest;
use App\Http\Requests\DeleteItemForPurchaseInvoiceRequest;
use App\Http\Requests\ListPurchaseInvoiceRequest;
use App\Http\Requests\RemoveOverallDiscountForPurchaseInvoiceRequest;
use App\Http\Requests\SavePurchaseInvoiceResource;
use App\Http\Requests\StorePurchaseInvoiceRequest;
use App\Http\Requests\UpdateAdditionalCostForPurchaseInvoiceRequest;
use App\Http\Requests\UpdateProductForPurchaseInvoiceRequest;
use App\Http\Requests\UpdateStatusForPurchaseInvoiceRequest;
use App\Http\Resources\PurchaseInvoiceResource;
use App\Models\AdditionalCost;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\TaxProfile;
use App\Models\Warehouse;
use App\Services\FilamentVariantBuilderService;
use App\Services\PricingService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends BaseController
{
    public function index(ListPurchaseInvoiceRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = Invoice::purchases()
            ->with(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns'])
            ->where('temp', 0)
            ->when($request->status, function (Builder $builder) use ($request) {
                return $builder->where('status', $request->status);
            })
            ->when($request->payment_method, function (Builder $builder) use ($request) {
                return $builder->where('payment_method', $request->payment_method);
            })
            ->when($request->transaction_ref, function (Builder $builder) use ($request) {
                return $builder->where('transaction_ref', $request->transaction_ref);
            })
            ->when($request->warehouse_id, function (Builder $builder) use ($request) {
                return $builder->where('warehouse_id', $request->warehouse_id);
            })
            ->when($request->discount_method, function (Builder $builder) use ($request) {
                return $builder->where('discount_method', $request->discount_method);
            })
            ->when($request->supplier_id, function (Builder $builder) use ($request) {
                return $builder->where('supplier_id', $request->supplier_id);
            })
            ->when($request->user_id, function (Builder $builder) use ($request) {
                return $builder->where('user_id', $request->user_id);
            })
            ->when($request->date, function (Builder $builder) use ($request) {
                return $builder->whereDate('date', $request->date);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })->when($sort, function (Builder $builder) use ($request, $sort) {
                if ($sort == 'oldest')
                    return $builder->orderBy('created_at');
                return $builder->orderByDesc('created_at');
            })
            ->get();

        if ($request->payment_status)
            $data = $data->filter(function (Invoice $invoice) use ($request) {
                return $invoice->getPaymentStatus("en") == $request->payment_status;
            });

        return $this->responder(__('messages.api.retrieved'), 200, PurchaseInvoiceResource::collection($data))
            ->respond();
    }

    public function store(StorePurchaseInvoiceRequest $request)
    {
        //create tmp purchase invoice

        $invoice = Invoice::create([
            'tenant_id' => $this->getTenantId(),
            'no' => generate_invoice_no(),
            'status' => 'purchase_order',
            'type' => 'purchases',
            'payment_method' => 'cash_on_delivery',
            'for' => 'supplier',
            'date' => now(),
            'warehouse_id' => Warehouse::first()?->id,
            'supplier_id' => Supplier::first()?->id,
            'user_id' => auth('sanctum')->id(),
            'temp' => true,
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'no' => $invoice->no,
            'uid' => $invoice->uid
        ])
            ->respond();
    }

    public function save(SavePurchaseInvoiceResource $request)
    {
        $invoice = Invoice::firstWhere('uid', $request->uid);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        if($invoice->items->isEmpty())
            return $this->errorBadRequest()->message(__('fields.invoice_must_at_least_have_one_product'))->respond();

        $data = $request->validated();

        $data['temp'] = false;

        if ($invoice->temp)
            $data['no'] = generate_invoice_no();

        $invoice->update($data);

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function addItem(AddProductForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        $product = Product::findOrFail($request->product_id);

        $product_variant_id = null;
        $name = $product->name;
        $taxProfile = TaxProfile::find($request->tax_profile_id);
        $discount = $request->input('discount', 0);
        $tax = 0;

        if ($taxProfile)
            $tax = PricingService::instance()->getTaxAmountFromProfile($taxProfile, $request->unit_cost, $request->qty);

        if ($request->type == "variants") {
            $productVariant = FilamentVariantBuilderService::instance(null, null, null)->getProductVariantByOptions($request->selected_variant_options_ids);

            if (!$productVariant)
                throw new \Exception("Product variant not found");

            $product_variant_id = $productVariant->id;
            $name = $productVariant->name;

        }

        if ($discount > 0) {
            $invoice->update(['discount_option' => 'per-item', 'discount_method' => 'amount']);
        }

        $invoice->items()->firstOrCreate(
            [
                'invoice_id' => $invoice->id,
                'product_id' => $request->product_id,
                'product_variant_id' => $product_variant_id,
            ],
            [
                'tenant_id' => $this->getTenantId(),
                'invoice_id' => $invoice->id,
                'product_id' => $request->product_id,
                'product_variant_id' => $product_variant_id,
                'name' => $name,
                'tax_profile_id' => $request->tax_profile_id,
                'tax_profile_data' => $taxProfile?->toArray(),
                'price' => $request->unit_cost,
                'discount' => $discount,
                'qty' => $request->qty,
                'tax' => $tax,
                'user_id' => auth('sanctum')->id()
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function updateItem(UpdateProductForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        $invoiceItem = InvoiceItem::findOrFail($request->item_id);

        $taxProfile = TaxProfile::find($request->tax_profile_id);
        $discount = $request->input('discount', 0);
        $tax = 0;

        if ($taxProfile)
            $tax = PricingService::instance()->getTaxAmountFromProfile($taxProfile, $request->unit_cost, $request->qty);

        if ($discount > 0) {
            $invoice->update(['discount_option' => 'per-item', 'discount_method' => 'amount']);
        }
        $invoice->items()->where('id', $invoiceItem->id)->update(
            [
                'tax_profile_id' => $request->tax_profile_id,
                'tax_profile_data' => $taxProfile?->toArray(),
                'price' => $request->unit_cost,
                'discount' => $discount,
                'qty' => $request->qty,
                'tax' => $tax,
                'user_id' => auth('sanctum')->id()
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function deleteItem(DeleteItemForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        InvoiceItem::findOrFail($request->item_id)->delete();

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function addAdditionalCost(AddAdditionalCostForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->additionalCosts()->firstOrCreate(
            [
                'additional_cost_type_id' => $request->additional_cost_type_id,
                'item_id' => $invoice->id,
                'item_type' => get_class($invoice),
            ],
            [
                'tenant_id' => $this->getTenantId(),
                'additional_cost_type_id' => $request->additional_cost_type_id,
                'item_id' => $invoice->id,
                'item_type' => get_class($invoice),
                'cost' => $request->cost,
                'statement' => $request->statement
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function updateAdditionalCost(UpdateAdditionalCostForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->additionalCosts()->where('id', $request->additional_cost_id)->update(
            [
                'additional_cost_type_id' => $request->additional_cost_type_id,
                'item_id' => $invoice->id,
                'item_type' => get_class($invoice),
                'cost' => $request->cost,
                'statement' => $request->statement
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function deleteAdditionalCost(DeleteAdditionalCostForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        AdditionalCost::findOrFail($request->additional_cost_id)->delete();

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }
    public function updateStatus(UpdateStatusForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        try {
            DB::beginTransaction();
            if ($request->status == "confirmed") {
                $invoice->confirmPurchaseInvoice();
            } else {
                $invoice->update(['status' => $request->status]);
            }
            $invoice->refresh();
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function applyOverallDiscount(ApplyOverallDiscountForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        try {
            DB::beginTransaction();

            $value = 0;

            if ($request->discount_method == "amount") {
                $value = $request->amount;
                $invoice->update(['discount_amount' => $value]);
            }
            if ($request->discount_method == "percent") {
                $totalPurchases = $invoice->items->sum(function ($i) {
                    return $i->qty * $i->price;
                });
                $value = $totalPurchases * ($request->percent / 100) / $invoice->items->count();
                $invoice->update(['discount_percent' => $request->percent]);
            }

            $invoice->items()->update(['discount' => $value / $invoice->items->count()]);

            $invoice->update(['discount_option' => 'overall', 'discount_method' => $request->discount_method]);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function removeOverallDiscount(RemoveOverallDiscountForPurchaseInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->purchase_invoice_uid)->firstOrFail();

        $invoice->load(['items.product', 'items.productVariant', 'items.taxProfile', 'items.purchasesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'purchasePayments', 'supplier', 'user', 'reviewedBy', 'stocks', 'purchasesReturns']);

        if ($invoice->status == "confirmed" or $invoice->status == "cancelled")
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        try {
            DB::beginTransaction();

            $invoice->update(['discount_option' => 'none', 'discount_method' => 'none', 'discount_amount' => null, 'discount_percent' => null]);

            $invoice->items()->update(['discount' => 0]);

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new PurchaseInvoiceResource($invoice))
            ->respond();
    }

    public function clearTempInvoices(): \Illuminate\Http\JsonResponse
    {
        $data = Invoice::purchases()->with('items')->where('temp', 1)->get();

        foreach ($data as $invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        }
        return $this->responder(__('messages.api.retrieved'), 200)
            ->respond();
    }

}
