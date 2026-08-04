<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListSalesReturnsRequest;
use App\Http\Requests\StoreSalesReturnsRequest;
use App\Http\Requests\UpdateSalesReturnsRequest;
use App\Http\Resources\InvoiceItemsForReturnsCreateResource;
use App\Http\Resources\SalesReturnsResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SalesReturns;
use App\Services\SalesReturnService;
use App\Services\SalesReturnWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnsController extends BaseController
{
    public function getAvailableInvoices(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [];

        foreach (SalesReturnWorkflow::returnableInvoiceOptions('sales', true) as $invoiceId => $label) {
            $invoice = Invoice::with('customer')->find($invoiceId);

            if (! $invoice) {
                continue;
            }

            $data[] = [
                'id' => $invoice->id,
                'no' => $invoice->no,
                'label' => $label,
                'customerId' => $invoice->customer_id,
                'customerName' => $invoice->customer?->name,
                'paymentTerms' => $invoice->payment_terms,
                'paidAmount' => (float) $invoice->getItemsCost(false, true, true),
            ];
        }

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function listInvoiceItemsForCreate(Request $request, $no): \Illuminate\Http\JsonResponse
    {
        $invoice = Invoice::query()->sales()->where('no', $no)->firstOrFail();

        $items = SalesReturnWorkflow::returnableInvoiceItemsQuery()
            ->where('invoice_id', $invoice->id)
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            InvoiceItemsForReturnsCreateResource::collection($items)
        )->respond();
    }

    public function returnableProductsForCustomer(int $customerId): \Illuminate\Http\JsonResponse
    {
        Customer::query()->findOrFail($customerId);

        $options = SalesReturnWorkflow::returnableProductOptionsForCustomer($customerId);
        $products = [];

        foreach ($options as $key => $name) {
            $products[] = [
                'productLineKey' => $key,
                'name' => $name,
                'returnableQty' => SalesReturnWorkflow::getReturnableProductQty($key, 'sales', $customerId),
            ];
        }

        return $this->responder(__('messages.api.retrieved'), 200, $products)->respond();
    }

    public function index(ListSalesReturnsRequest $request): \Illuminate\Http\JsonResponse
    {
        $query = SalesReturns::query()->with(['details.invoiceItem', 'invoice', 'customer', 'user']);

        $request->whenFilled('invoice_no', function () use ($query, $request) {
            $query->whereRelation('invoice', 'no', $request->invoice_no);
        });

        $request->whenFilled('client_id', function () use ($query, $request) {
            $query->where(function (Builder $builder) use ($request) {
                $builder
                    ->where('customer_id', $request->client_id)
                    ->orWhereRelation('invoice', 'customer_id', $request->client_id);
            });
        });

        $query->when(filled($request->from_date) || filled($request->to_date), function (Builder $builder) use ($request) {
            $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, 'd-m-Y');
        });

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            SalesReturnsResource::collection($query->orderByDesc('id')->get())
        )->respond();
    }

    public function show($id): \Illuminate\Http\JsonResponse
    {
        $salesReturn = SalesReturns::query()
            ->with(['details.invoiceItem', 'invoice', 'customer', 'user'])
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new SalesReturnsResource($salesReturn))->respond();
    }

    public function store(StoreSalesReturnsRequest $request)
    {
        try {
            $salesReturn = SalesReturnService::instance()->create(
                $request->all(),
                $this->getTenantId(),
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.created'), 201, new SalesReturnsResource($salesReturn))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function update(UpdateSalesReturnsRequest $request, $id)
    {
        $salesReturns = SalesReturns::findOrFail($id);

        try {
            if ($request->filled('notes')) {
                $salesReturns->update(['notes' => $request->notes]);
            }

            $salesReturns->load(['details.invoiceItem', 'invoice', 'customer', 'user']);

            return $this->responder(__('messages.api.updated'), 200, new SalesReturnsResource($salesReturns))->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function destroy(string $id)
    {
        $item = SalesReturns::findOrFail($id);
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
