<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListPurchasesReturnsRequest;
use App\Http\Requests\StorePurchasesReturnsRequest;
use App\Http\Requests\UpdatePurchaseReturnsRequest;
use App\Http\Resources\InvoiceItemsForReturnsCreateResource;
use App\Http\Resources\PurchasesReturnsResource;
use App\Models\Invoice;
use App\Models\PurchasesReturns;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use App\Services\SalesReturnWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasesReturnsController extends BaseController
{
    public function getAvailableInvoices(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];

        foreach (SalesReturnWorkflow::returnableInvoiceOptions('purchases', true) as $invoiceId => $label) {
            $invoice = Invoice::with('supplier')->find($invoiceId);

            if (! $invoice) {
                continue;
            }

            $data[] = [
                'id' => $invoice->id,
                'no' => $invoice->no,
                'label' => $label,
                'supplierId' => $invoice->supplier_id,
                'supplierName' => $invoice->supplier?->name,
                'paymentTerms' => $invoice->payment_terms,
                'paidAmount' => (float) $invoice->getItemsCost(false, true, true),
            ];
        }

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function listInvoiceItemsForCreate(Request $request, $no): \Illuminate\Http\JsonResponse
    {
        $invoice = Invoice::query()->purchases()->where('no', $no)->firstOrFail();

        $items = SalesReturnWorkflow::returnableInvoiceItemsQuery()
            ->where('invoice_id', $invoice->id)
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            InvoiceItemsForReturnsCreateResource::collection($items)
        )->respond();
    }

    public function returnableProductsForSupplier(int $supplierId): \Illuminate\Http\JsonResponse
    {
        Supplier::query()->findOrFail($supplierId);

        $options = SalesReturnWorkflow::returnableProductOptionsForSupplier($supplierId);
        $products = [];

        foreach ($options as $key => $name) {
            $products[] = [
                'productLineKey' => $key,
                'name' => $name,
                'returnableQty' => SalesReturnWorkflow::getReturnableProductQty($key, 'purchases', supplierId: $supplierId),
            ];
        }

        return $this->responder(__('messages.api.retrieved'), 200, $products)->respond();
    }

    public function index(ListPurchasesReturnsRequest $request): \Illuminate\Http\JsonResponse
    {
        $query = PurchasesReturns::query()->with(['details.invoiceItem', 'invoice.supplier', 'supplier', 'user']);

        $request->whenFilled('invoice_no', function () use ($query, $request) {
            $query->whereRelation('invoice', 'no', $request->invoice_no);
        });

        $request->whenFilled('supplier_id', function () use ($query, $request) {
            $query->where(function (Builder $builder) use ($request) {
                $builder
                    ->where('supplier_id', $request->supplier_id)
                    ->orWhereRelation('invoice', 'supplier_id', $request->supplier_id);
            });
        });

        $query->when(filled($request->from_date) || filled($request->to_date), function (Builder $builder) use ($request) {
            $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, 'd-m-Y');
        });

        $request->whenFilled('q', function () use ($query, $request) {
            $term = '%' . $request->q . '%';
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('notes', 'like', $term)
                    ->orWhereRelation('invoice', 'no', 'like', $term)
                    ->orWhereRelation('supplier', 'name', 'like', $term)
                    ->orWhereRelation('invoice.supplier', 'name', 'like', $term);
            });
        });

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            PurchasesReturnsResource::collection($query->orderByDesc('id')->get())
        )->respond();
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $purchaseReturn = PurchasesReturns::query()
            ->with(['details.invoiceItem', 'invoice.supplier', 'supplier', 'user'])
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new PurchasesReturnsResource($purchaseReturn))->respond();
    }

    public function store(StorePurchasesReturnsRequest $request)
    {
        try {
            $purchaseReturn = PurchaseReturnService::instance()->create(
                $request->all(),
                $this->getTenantId(),
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.created'), 201, new PurchasesReturnsResource($purchaseReturn))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function update(UpdatePurchaseReturnsRequest $request, $id)
    {
        $purchasesReturns = PurchasesReturns::findOrFail($id);

        try {
            if ($request->filled('notes')) {
                $purchasesReturns->update(['notes' => $request->notes]);
            }

            $purchasesReturns->load(['details.invoiceItem', 'invoice.supplier', 'supplier', 'user']);

            return $this->responder(__('messages.api.updated'), 200, new PurchasesReturnsResource($purchasesReturns))->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function destroy(string $id)
    {
        $item = PurchasesReturns::findOrFail($id);
        abort_if(! $this->canDelete($item), 403, __('messages.api.permission_denied'));

        try {
            DB::transaction(function () use ($item) {
                $item->details()->delete();
                $item->delete();
            });

            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
