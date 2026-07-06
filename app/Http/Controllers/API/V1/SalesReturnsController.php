<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListSalesReturnsRequest;
use App\Http\Requests\StoreSalesReturnsRequest;
use App\Http\Requests\UpdateSalesReturnsRequest;
use App\Http\Resources\InvoiceItemResource;
use App\Http\Resources\InvoiceItemsForReturnsCreateResource;
use App\Http\Resources\SalesReturnsResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SalesReturns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnsController extends BaseController
{
    public function getAvailableInvoices(Request $request): \Illuminate\Http\JsonResponse
    {
        $invoices = Invoice::with(['customer', 'salesReturns'])->doesntHave('salesReturns')->sales()->where('temp', 0)->get();

        $data = [];

        foreach ($invoices as $invoice) {
            $data[$invoice->no] = $invoice->no . " - " . $invoice->customer->name;
        }

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function listInvoiceItemsForCreate(Request $request, $no): \Illuminate\Http\JsonResponse
    {
        $invoice = Invoice::with(['items'])->sales()->where('no', $no)->firstOrFail();

        return $this->responder(__('messages.api.retrieved'), 200, InvoiceItemsForReturnsCreateResource::collection($invoice->items))->respond();
    }

    public function index(ListSalesReturnsRequest $request): \Illuminate\Http\JsonResponse
    {
        $query = SalesReturns::query()->with(['details.invoiceItem', 'invoice', 'user']);

        $request->whenFilled('invoice_no', function () use ($query, $request) {
            $query->whereRelation('invoice', 'no', $request->invoice_no);
        });

        $request->whenFilled('client_id', function () use ($query, $request) {
            $query->whereRelation('invoice', 'customer_id', $request->client_id);
        });

        $query->when(filled($request->from_date) || filled($request->to_date), function (Builder $builder) use ($request) {
            $builder->whereDateBetween('date', $request->from_date, $request->to_date, 'd-m-Y');
        });

        return $this->responder(__('messages.api.retrieved'), 200, SalesReturnsResource::collection($query->get()->sortByDesc('created_at')))->respond();
    }

    public function store(StoreSalesReturnsRequest $request)
    {
        $invoice = Invoice::sales()->doesntHave('salesReturns')->where('no', $request->invoice_no)->firstOrFail();

        try {
            DB::beginTransaction();

            $salesReturns = SalesReturns::create([
                'tenant_id' => $this->getTenantId(),
                'invoice_id' => $invoice->id,
                'notes' => $request->notes,
                'user_id' => auth('sanctum')->id(),
            ]);

            foreach ($request->input('items', []) as $item) {
                $invoiceItem = InvoiceItem::findOrFail($item['id']);
                if ($item['qty'] > $invoiceItem->qty)
                    return $this->errorBadRequest()->message("Invalid qty")->respond();

                $salesReturns->details()->create([
                    'tenant_id' => $this->getTenantId(),
                    'sales_returns_id' => $salesReturns->id,
                    'invoice_item_id' => $item['id'],
                    'qty' => $item['qty'],
                    'user_id' => auth('sanctum')->id(),
                ]);
            }

            $salesReturns->load(['details.invoiceItem', 'invoice', 'user']);

            DB::commit();

            return $this->responder(__('messages.api.retrieved'), 200, new SalesReturnsResource($salesReturns))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }

    }

    public function update(UpdateSalesReturnsRequest $request, $id)
    {
        $salesReturns = SalesReturns::findOrFail($id);

        try {
            DB::beginTransaction();

            $request->whenFilled('notes', function () use ($salesReturns, $request) {
                $salesReturns->update([
                    'notes' => $request->notes,
                ]);
            });

            $salesReturns->details()->delete();

            foreach ($request->input('items', []) as $item) {
                $invoiceItem = InvoiceItem::findOrFail($item['id']);
                if ($item['qty'] > $invoiceItem->qty)
                    return $this->errorBadRequest()->message("Invalid qty")->respond();

                $salesReturns->details()->create([
                    'tenant_id' => $this->getTenantId(),
                    'sales_returns_id' => $salesReturns->id,
                    'invoice_item_id' => $item['id'],
                    'qty' => $item['qty'],
                    'user_id' => auth('sanctum')->id(),
                ]);
            }

            $salesReturns->load(['details.invoiceItem', 'invoice', 'user']);

            DB::commit();

            return $this->responder(__('messages.api.retrieved'), 200, new SalesReturnsResource($salesReturns))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = SalesReturns::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->details()->delete();
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
