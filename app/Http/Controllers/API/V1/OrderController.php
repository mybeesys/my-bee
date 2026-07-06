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
        $data = Order::with(['tenant', 'details.orderDetailsExtras', 'customer', 'invoice.items.extras', 'invoice.receiptVoucher'])
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
            ->orderByDesc('created_at')
            ->get();

        if ($request->has('payment_status')) {
            $data = $data->filter(function ($order) use ($request) {
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
            app(\App\Services\OrderStatusService::class)->applyStatusChange($record, $data);

            $record->refresh();

            fns()->saved();

        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }

        return $this->responder(__('messages.api.updated'), 200, new OrderResource($record))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Order::with(['tenant', 'details.orderDetailsExtras', 'customer', 'invoice.items.extras', 'invoice.receiptVoucher'])
            ->findOrFail($id);
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
            report($exception);
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
