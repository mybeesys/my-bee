<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\CustomerAccountStatementRequest;
use App\Http\Requests\ListCustomerInvoicesRequest;
use App\Http\Requests\ListCustomerOrdersRequest;
use App\Http\Requests\ListCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerAccountStatementResource;
use App\Http\Resources\CustomerInvoiceListResource;
use App\Http\Resources\CustomerOrderListResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\CustomerAccountStatementService;
use Illuminate\Database\Eloquent\Builder;

class CustomerController extends BaseController
{
    public function __construct(
        protected CustomerAccountStatementService $statementService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListCustomerRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = Customer::query()
            ->with(['state', 'city.state', 'area', 'acc4'])
            ->withCount([
                'orders',
                'invoices as invoices_count' => function (Builder $query) {
                    $query->where('type', 'sales')->where('status', 'confirmed');
                },
            ])
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('no', 'like', "%{$search}%")
                        ->orWhere('trn', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'))
            ->get();

        $payload = collect(CustomerResource::collection($data)->resolve());

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;
        $data['auto_registered'] = false;

        $customer = Customer::create($data);
        $customer->load(['state', 'city.state', 'area', 'acc4']);

        return $this->responder(__('messages.api.created'), 201, new CustomerResource($customer))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Customer::with(['state', 'city.state', 'area', 'acc4'])
            ->withCount([
                'orders',
                'invoices as invoices_count' => function (Builder $query) {
                    $query->where('type', 'sales')->where('status', 'confirmed');
                },
            ])
            ->findOrFail($id);

        $resource = (new CustomerResource($item))->additional([
            'overview' => $this->overviewPayload($item),
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, $resource)->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, string $id)
    {
        $customer = Customer::with(['state', 'city', 'area', 'acc4'])->findOrFail($id);
        $customer->update($request->validated());
        $customer->refresh()->load(['state', 'city.state', 'area', 'acc4']);

        return $this->responder(__('messages.api.updated'), 200, new CustomerResource($customer))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Customer::findOrFail($id);
        abort_if(! $this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();

            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }

    /**
     * Account statement for a customer (same as the web view page).
     */
    public function accountStatement(CustomerAccountStatementRequest $request, string $id)
    {
        $customer = Customer::query()->findOrFail($id);

        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));

        $statement = $this->statementService->build($customer, $from, $to);

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            new CustomerAccountStatementResource($statement)
        )->respond();
    }

    /**
     * Sales invoices tab on the customer view page.
     */
    public function invoices(ListCustomerInvoicesRequest $request, string $id)
    {
        $customer = Customer::query()->findOrFail($id);

        $invoices = Invoice::query()
            ->sales()
            ->listedInSalesModule()
            ->where('customer_id', $customer->id)
            ->with(['order', 'salesPayments', 'additionalCosts', 'receiptVoucher', 'items', 'services'])
            ->withCount('salesReturns')
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('no', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest()
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            CustomerInvoiceListResource::collection($invoices)
        )->respond();
    }

    /**
     * Orders tab on the customer view page.
     */
    public function orders(ListCustomerOrdersRequest $request, string $id)
    {
        $customer = Customer::query()->findOrFail($id);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with(['invoice.salesPayments', 'customer'])
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('no', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest()
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            CustomerOrderListResource::collection($orders)
        )->respond();
    }

    /**
     * Summary cards on the customer overview tab.
     *
     * @return array<string, mixed>
     */
    protected function overviewPayload(Customer $customer): array
    {
        $from = now()->startOfYear()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $statement = $this->statementService->build($customer, $from, $to);

        if ($statement === null) {
            return [
                'hasAccount' => false,
                'message' => __('fields.customer_account_statement_no_account'),
                'ordersCount' => $customer->orders()->count(),
                'invoicesCount' => $customer->invoices()->where('type', 'sales')->where('status', 'confirmed')->count(),
                'unpaidTotal' => 0.0,
                'currentBalance' => 0.0,
                'currency' => main_currency_iso_code(),
            ];
        }

        return [
            'hasAccount' => true,
            'accountCode' => (string) $statement['account_code'],
            'ordersCount' => (int) $statement['orders_count'],
            'invoicesCount' => (int) $statement['invoices_count'],
            'unpaidTotal' => (float) $statement['unpaid_total'],
            'currentBalance' => (float) $statement['current_balance'],
            'currency' => $statement['currency'],
        ];
    }
}
