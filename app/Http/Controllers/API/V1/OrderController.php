<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRecordRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReceiptVoucherResource;
use App\Models\Order;
use App\Models\ReceiptVoucher;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends BaseController
{
    public function __construct(
        protected OrderService $orders,
        protected OrderStatusService $orderStatus,
    ) {
    }

    public function index(ListOrderRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $query = Order::query()
            ->with(OrderService::eagerLoads())
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('no', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->no, fn (Builder $builder) => $builder->where('no', $request->no))
            ->when($request->source, fn (Builder $builder) => $builder->where('source', $request->source))
            ->when($request->payment_method, fn (Builder $builder) => $builder->where('payment_method', $request->payment_method))
            ->when($request->status, fn (Builder $builder) => $builder->where('status', $request->status))
            ->when($request->filled('statuses'), fn (Builder $builder) => $builder->whereIn('status', $request->input('statuses')))
            ->when($request->filled('customer_ids'), fn (Builder $builder) => $builder->whereIn('customer_id', $request->input('customer_ids')))
            ->when($request->delivery_type, fn (Builder $builder) => $builder->where('delivery_type', $request->delivery_type))
            ->when($request->state_id, fn (Builder $builder) => $builder->where('state_id', $request->state_id))
            ->when($request->city_id, fn (Builder $builder) => $builder->where('city_id', $request->city_id))
            ->when($request->area_id, fn (Builder $builder) => $builder->where('area_id', $request->area_id))
            ->when($request->coupon, fn (Builder $builder) => $builder->whereRelation('coupon', 'code', $request->coupon))
            ->when($request->filled('from_date'), function (Builder $builder) use ($request) {
                $builder->whereDate('created_at', '>=', Carbon::parse($request->input('from_date'))->format('Y-m-d'));
            })
            ->when($request->filled('to_date'), function (Builder $builder) use ($request) {
                $builder->whereDate('created_at', '<=', Carbon::parse($request->input('to_date'))->format('Y-m-d'));
            })
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'));

        $data = $query->get();

        if ($request->has('payment_status')) {
            $data = $data->filter(fn (Order $order) => $order->invoice?->payment_status === $request->payment_status);
        }

        $payload = collect(OrderResource::collection($data)->resolve());

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    public function stats()
    {
        return $this->responder(__('messages.api.retrieved'), 200, $this->orders->stats())->respond();
    }

    public function store(StoreOrderRequest $request)
    {
        if (! plan_allows_store()) {
            return $this->errorBadRequest()
                ->message(__('fields.store_not_available_on_plan'))
                ->respond();
        }

        if (subscription_resource_maxed_out('orders', $this->getTenant(false)->client)) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('orders', $this->getTenant(false)->client))
                ->respond();
        }

        try {
            $order = $this->orders->create(
                $request->validated(),
                $this->getTenant()->id,
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.created'), 201, new OrderResource($order))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        }
    }

    public function show(string $id)
    {
        $item = Order::with(OrderService::eagerLoads())->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new OrderResource($item))->respond();
    }

    public function replace(UpdateOrderRecordRequest $request, string $id)
    {
        $record = Order::with(OrderService::eagerLoads())->findOrFail($id);

        try {
            $order = $this->orders->replace(
                $record,
                $request->validated(),
                $this->getTenant()->id,
                (int) auth('sanctum')->id(),
            );

            fns()->saved();

            return $this->responder(__('messages.api.updated'), 200, new OrderResource($order))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function update(UpdateOrderRequest $request, string $id)
    {
        $data = $request->validated();
        $record = Order::with(OrderService::eagerLoads())->findOrFail($id);

        try {
            $payload = collect($data)->mapWithKeys(function ($value, $key) {
                if (in_array($key, ['delivery_date', 'canceled_date'], true) && filled($value)) {
                    return [$key => Carbon::parse($value)];
                }

                return [$key => $value];
            })->all();

            $this->orderStatus->applyStatusChange($record, $payload);
            $record->refresh()->load(OrderService::eagerLoads());

            fns()->saved();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }

        return $this->responder(__('messages.api.updated'), 200, new OrderResource($record))->respond();
    }

    public function confirmInvoice(string $id)
    {
        $record = Order::with(OrderService::eagerLoads())->findOrFail($id);

        try {
            $order = $this->orders->confirmInvoice($record);

            return $this->responder(__('fields.invoice_confirmed_successfully'), 200, new OrderResource($order))->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function destroy(string $id)
    {
        $item = Order::findOrFail($id);
        abort_if(! $this->canDelete($item), 403, __('messages.api.permission_denied'));

        try {
            $item->delete();

            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            report($exception);

            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }

    public function payments(Request $request, string $id)
    {
        $order = Order::with(['invoice.receiptVoucher', 'invoice.salesPayments'])->findOrFail($id);
        $invoice = $order->invoice;
        $receiptVoucherId = null;
        $receiptVoucher = null;

        if ($invoice) {
            $receiptVoucher = $invoice->relationLoaded('receiptVoucher') && $invoice->receiptVoucher
                ? $invoice->receiptVoucher
                : ReceiptVoucher::findForInvoice($invoice->id);
            $receiptVoucherId = $receiptVoucher?->id;
        }

        return $this->responder(__('messages.api.retrieved'), 200, [
            'orderId' => $order->id,
            'invoiceId' => $invoice?->id,
            'invoiceNo' => $invoice?->no,
            'isPaid' => (bool) $invoice?->paid,
            'paymentStatus' => $invoice?->payment_status,
            'receiptVoucherId' => $receiptVoucherId,
            'canCompletePayment' => $invoice && ! $invoice->paid,
            'receiptVoucher' => $receiptVoucher ? new ReceiptVoucherResource($receiptVoucher) : null,
        ])->respond();
    }
}
