<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Models\AdditionalCost;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListOrderRequest $request)
    {
        $data = Order::with(['tenant', 'details.item', 'details.orderDetailsExtras', 'customer', 'invoice'])
            ->when($request->no, function (Builder $builder) use ($request) {
                return $builder->where('no', $request->no);
            })
            ->when($request->source, function (Builder $builder) use ($request) {
                return $builder->where('source', $request->source);
            })
            ->when($request->payment_method, function (Builder $builder) use ($request) {
                return $builder->where('payment_method', $request->payment_method);
            })
            ->when($request->status, function (Builder $builder) use ($request) {
                return $builder->where('status', $request->status);
            })
            ->when($request->delivery_type, function (Builder $builder) use ($request) {
                return $builder->where('delivery_type', $request->delivery_type);
            })
            ->when($request->state_id, function (Builder $builder) use ($request) {
                return $builder->where('state_id', $request->state_id);
            })
            ->when($request->city_id, function (Builder $builder) use ($request) {
                return $builder->where('city_id', $request->city_id);
            })
            ->when($request->area_id, function (Builder $builder) use ($request) {
                return $builder->where('area_id', $request->area_id);
            })
            ->when($request->coupon, function (Builder $builder) use ($request) {
                return $builder->whereRelation('coupon', 'code', $request->coupon);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->get();

        if($request->has('payment_status')){
            $data = $data->filter(function ($order) use ($request){
                return $order->invoice->payment_status == $request->payment_status;
            });
        }
        return $this->responder(__('messages.api.retrieved'), 200, OrderResource::collection($data))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request, string $id)
    {
        $data = $request->validated();

        $record = Order::findOrFail($id);

        try {
            DB::beginTransaction();

            if (array_key_exists('delivery', $data)) {
                //sync additional cost
                $invoice = $record->invoice;
                $invAdditionalCost = AdditionalCost::where('meta->type', 'delivery_fees')->where('item_type', Invoice::class)->where('item_id', $invoice->id)->first();

                if ($invAdditionalCost) {
                    $invAdditionalCost->update([
                        'cost' => $data['delivery'],
                    ]);
                }

            }

            if ($data['status'] == Order::$STATUS_CANCELLED) {
                //cancel invoice
                $record->invoice->update([
                    'status' => 'cancelled',
                    'locked_by_id' => auth('sanctum')->id(),
                    'locked_at' => now(),
                ]);
            }

            if ($data['status'] == Order::$STATUS_COMPLETED) {
                //confirmed invoice
                $record->update(['delivery_date' => $data['delivery_date']]);

                $record->invoice->update([
                    'status' => 'confirmed',
                    'locked_by_id' => auth('sanctum')->id(),
                    'locked_at' => now(),
                ]);

                StockService::instance()->takeStockFromSalesInvoice($record->invoice);

            }

            $record->update($data);

            $record->refresh();

            DB::commit();

            fns()->saved();

        } catch (\Throwable $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }

        return $this->responder(__('messages.api.updated'), 200, new OrderResource($record))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Order::findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new OrderResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Order::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function payments(Request $request, string $id)
    {
        $order = Order::with(['invoice.salesPayments', 'details.item', 'details.orderDetailsExtras', 'customer'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, OrderResource::collection($order))->respond();
    }
}
