<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListPurchasesReturnsRequest;
use App\Http\Requests\StorePurchasesReturnsRequest;
use App\Http\Requests\UpdatePurchaseReturnsRequest;
use App\Http\Resources\InvoiceItemsForReturnsCreateResource;
use App\Http\Resources\PurchasesReturnsResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchasesReturns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchasesReturnsController extends BaseController
{
    public function getAvailableInvoices(Request $request): \Illuminate\Http\JsonResponse
    {
        $invoices = Invoice::with(['supplier', 'purchasesReturns'])->doesntHave('purchasesReturns')->purchases()->where('temp', 0)->get();

        $data = [];

        foreach ($invoices as $invoice) {
            $data[$invoice->no] = $invoice->no . " - " . $invoice->supplier->name;
        }

        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }

    public function listInvoiceItemsForCreate(Request $request, $no): \Illuminate\Http\JsonResponse
    {
        $invoice = Invoice::with(['items'])->purchases()->where('no', $no)->firstOrFail();

        return $this->responder(__('messages.api.retrieved'), 200, InvoiceItemsForReturnsCreateResource::collection($invoice->items))->respond();
    }

    public function index(ListPurchasesReturnsRequest $request): \Illuminate\Http\JsonResponse
    {
        $query = PurchasesReturns::query()->with(['details.invoiceItem', 'invoice', 'user']);

        $request->whenFilled('invoice_no', function () use ($query, $request) {
            $query->whereRelation('invoice', 'no', $request->invoice_no);
        });

        $request->whenFilled('supplier_id', function () use ($query, $request) {
            $query->whereRelation('invoice', 'supplier_id', $request->supplier_id);
        });

        return $this->responder(__('messages.api.retrieved'), 200, PurchasesReturnsResource::collection($query->get()->sortByDesc('created_at')))->respond();
    }

    public function store(StorePurchasesReturnsRequest $request)
    {
        $invoice = Invoice::purchases()->doesntHave('purchasesReturns')->where('no', $request->invoice_no)->firstOrFail();

        try {
            DB::beginTransaction();

            $purchasesReturns = PurchasesReturns::create([
                'tenant_id' => $this->getTenantId(),
                'invoice_id' => $invoice->id,
                'notes' => $request->notes,
                'user_id' => auth('sanctum')->id(),
            ]);

            foreach ($request->input('items', []) as $item) {
                $invoiceItem = InvoiceItem::findOrFail($item['id']);
                if ($item['qty'] > $invoiceItem->qty)
                    return $this->errorBadRequest()->message("Invalid qty")->respond();

                $purchasesReturns->details()->create([
                    'tenant_id' => $this->getTenantId(),
                    'purchases_returns_id' => $purchasesReturns->id,
                    'invoice_item_id' => $item['id'],
                    'qty' => $item['qty'],
                    'user_id' => auth('sanctum')->id(),
                ]);
            }

            $purchasesReturns->load(['details.invoiceItem', 'invoice', 'user']);

            DB::commit();

            return $this->responder(__('messages.api.retrieved'), 200, new PurchasesReturnsResource($purchasesReturns))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);

            return $this->error($exception)->respond();
        }

    }

    public function update(UpdatePurchaseReturnsRequest $request, $id)
    {
        $purchasesReturns = PurchasesReturns::findOrFail($id);

        try {
            DB::beginTransaction();

            $request->whenFilled('notes', function () use ($purchasesReturns, $request) {
                $purchasesReturns->update([
                    'notes' => $request->notes,
                ]);
            });

            $purchasesReturns->details()->delete();

            foreach ($request->input('items', []) as $item) {
                $invoiceItem = InvoiceItem::findOrFail($item['id']);
                if ($item['qty'] > $invoiceItem->qty)
                    return $this->errorBadRequest()->message("Invalid qty")->respond();

                $purchasesReturns->details()->create([
                    'tenant_id' => $this->getTenantId(),
                    'purchases_returns_id' => $purchasesReturns->id,
                    'invoice_item_id' => $item['id'],
                    'qty' => $item['qty'],
                    'user_id' => auth('sanctum')->id(),
                ]);
            }

            $purchasesReturns->load(['details.invoiceItem', 'invoice', 'user']);

            DB::commit();

            return $this->responder(__('messages.api.retrieved'), 200, new PurchasesReturnsResource($purchasesReturns))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();]
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = PurchasesReturns::findOrFail($id);
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
