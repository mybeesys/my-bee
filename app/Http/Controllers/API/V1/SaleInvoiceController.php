<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\AddAdditionalCostForSalesInvoiceRequest;
use App\Http\Requests\AddProductForSalesInvoiceRequest;
use App\Http\Requests\AddServiceForSalesInvoiceRequest;
use App\Http\Requests\ApplyOverallDiscountForSalesInvoiceRequest;
use App\Http\Requests\DeleteAdditionalCostForSalesInvoiceRequest;
use App\Http\Requests\DeleteProductForSalesInvoiceRequest;
use App\Http\Requests\DeleteServiceForSalesInvoiceRequest;
use App\Http\Requests\ListSalesInvoiceRequest;
use App\Http\Requests\RemoveOverallDiscountForSalesInvoiceRequest;
use App\Http\Requests\SaveSalesInvoiceRequest;
use App\Http\Requests\UpdateAdditionalCostForSalesInvoiceRequest;
use App\Http\Requests\UpdateProductForSalesInvoiceRequest;
use App\Http\Requests\UpdateServiceForSalesInvoiceRequest;
use App\Http\Requests\UpdateStatusForSalesInvoiceRequest;
use App\Http\Resources\SalesInvoiceResource;
use App\Models\AdditionalCost;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemExtra;
use App\Models\Product;
use App\Models\ProductExtra;
use App\Models\Service;
use App\Models\TaxProfile;
use App\Services\FilamentVariantBuilderService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaleInvoiceController extends BaseController
{
    public function index(ListSalesInvoiceRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = Invoice::sales()
            ->with(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns'])
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
            ->when($request->discount_method, function (Builder $builder) use ($request) {
                return $builder->where('discount_method', $request->discount_method);
            })
            ->when($request->customer_id, function (Builder $builder) use ($request) {
                return $builder->where('customer_id', $request->customer_id);
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

        return $this->responder(__('messages.api.retrieved'), 200, SalesInvoiceResource::collection($data))
            ->respond();
    }

    public function store()
    {
        //create tmp sales invoice

        $invoice = Invoice::create([
            'tenant_id' => $this->getTenantId(),
            'no' => generate_invoice_no(),
            'status' => 'confirmed',
            'type' => 'sales',
            'payment_method' => 'cash_on_delivery',
            'for' => 'customer',
            'date' => now(),
            'customer_id' => Customer::first()?->id,
            'user_id' => auth('sanctum')->id(),
            'temp' => true,
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'no' => $invoice->no,
            'uid' => $invoice->uid
        ])
            ->respond();
    }

    public function save(SaveSalesInvoiceRequest $request)
    {
        $invoice = Invoice::firstWhere('uid', $request->uid);

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        if($invoice->items->isEmpty())
            return $this->errorBadRequest()->message(__('fields.invoice_must_at_least_have_one_product'))->respond();

        if ($invoice->temp && subscription_resource_maxed_out('sales_invoices', $this->getTenant(false)->client)) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('sales_invoices', $this->getTenant(false)->client))
                ->respond();
        }

        $data = $request->validated();

        $data['temp'] = false;

        if ($invoice->temp)
            $data['no'] = generate_invoice_no();

        $invoice->update($data);

        $invoice->refresh()->load('items');

        if ($invoice->isEditable()) {
            $invoice->confirmSalesInvoice();
            $invoice->refresh();
        }

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function addItem(AddProductForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        $product = Product::findOrFail($request->product_id);

        $product_variant_id = null;
        $name = $product->name;
        $taxProfile = TaxProfile::find($request->tax_profile_id);
        $tax = 0;
        $discount = $request->input('discount', 0);

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

        $invItem = $invoice->items()->firstOrCreate(
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
        foreach ($request->input('extras', []) as $product_extra_id)
        {
            $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($product_extra_id);

            $invItem->extras()->create([
                'tenant_id' => $this->getTenantId(),
                'invoice_item_id' => $invItem->id,
                'product_extra_id' => $productExtra->id,
                'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                'display_name' => $productExtra->extra->name,
                'qty' => 1,
            ]);
        }

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function updateItem(UpdateProductForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        $invoiceItem = InvoiceItem::findOrFail($request->item_id);

        $taxProfile = TaxProfile::find($request->tax_profile_id);
        $tax = 0;
        $discount = $request->input('discount', 0);

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

        $invItem = $invoice->items->where('id', $invoiceItem->id)->first();

        InvoiceItemExtra::whereIn('id', $invItem->extras->pluck('id')->toArray())->delete();

        foreach ($request->input('extras', []) as $product_extra_id)
        {
            $productExtra = ProductExtra::with(['lastPrice', 'extra'])->findOrFail($product_extra_id);

           InvoiceItemExtra::create([
                'tenant_id' => $this->getTenantId(),
                'invoice_item_id' => $invItem->id,
                'product_extra_id' => $productExtra->id,
                'unit_price' => PricingService::instance()->getRetailPrice($productExtra),
                'display_name' => $productExtra->extra->name,
                'qty' => 1,
            ]);
        }
        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function deleteItem(DeleteProductForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        InvoiceItem::findOrFail($request->item_id)->delete();

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function addAdditionalCost(AddAdditionalCostForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->additionalCosts()->create(
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

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function updateAdditionalCost(UpdateAdditionalCostForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
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

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function deleteAdditionalCost(DeleteAdditionalCostForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        AdditionalCost::findOrFail($request->additional_cost_id)->delete();

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function addService(AddServiceForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->services()->create(
            [
                'tenant_id' => $this->getTenantId(),
                'service_type_id' => $request->service_type_id,
                'item_id' => $invoice->id,
                'item_type' => get_class($invoice),
                'price' => $request->price,
                'description' => $request->description,
                'tax_profile_id' => TaxProfile::find($request->tax_profile_id)?->id,
                'tax_profile_data' => TaxProfile::find($request->tax_profile_id)?->toArray(),
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function updateService(UpdateServiceForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->services()->where('id', $request->service_id)->update(
            [
                'service_type_id' => $request->service_type_id,
                'price' => $request->price,
                'description' => $request->description,
                'tax_profile_id' => TaxProfile::find($request->tax_profile_id)?->id,
                'tax_profile_data' => TaxProfile::find($request->tax_profile_id)?->toArray(),
            ]
        );

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function deleteService(DeleteServiceForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        Service::findOrFail($request->service_id)->delete();

        $invoice->refresh();

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }
    public function updateStatus(UpdateStatusForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
            return $this->errorBadRequest()->message(__('fields.invoice_locked_statement'))->respond();

        if (
            $request->status === 'confirmed'
            && $invoice->temp
            && subscription_resource_maxed_out('sales_invoices', $this->getTenant(false)->client)
        ) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('sales_invoices', $this->getTenant(false)->client))
                ->respond();
        }

        try {
            DB::beginTransaction();
            if ($request->status == "confirmed") {
                $invoice->confirmSalesInvoice();
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

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function applyOverallDiscount(ApplyOverallDiscountForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
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

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function removeOverallDiscount(RemoveOverallDiscountForSalesInvoiceRequest $request)
    {
        $invoice = Invoice::where('uid', $request->sale_invoice_uid)->firstOrFail();

        $invoice->load(['services.type', 'services.taxProfile', 'items.product', 'items.productVariant', 'items.taxProfile', 'items.salesReturnsDetails', 'items.invoice', 'items.user', 'additionalCosts.type', 'salesPayments', 'customer', 'user', 'reviewedBy', 'stocks', 'salesReturns']);

        if ($invoice->isLocked())
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

        return $this->responder(__('messages.api.retrieved'), 200, new SalesInvoiceResource($invoice))
            ->respond();
    }

    public function clearTempInvoices(): \Illuminate\Http\JsonResponse
    {
        $data = Invoice::sales()->with('items')->where('temp', 1)->get();

        foreach ($data as $invoice) {
            $invoice->items()->delete();
            $invoice->delete();
        }
        return $this->responder(__('messages.api.retrieved'), 200)
            ->respond();
    }
}
