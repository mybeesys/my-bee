<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListSupplierPurchaseInvoicesRequest;
use App\Http\Requests\ListSupplierRequest;
use App\Http\Requests\ListSupplierSupplyOrdersRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\SupplierAccountStatementRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierAccountStatementResource;
use App\Http\Resources\SupplierPurchaseInvoiceListResource;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\SupplierSupplyOrderListResource;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\SupplyOrder;
use App\Services\SupplierAccountStatementService;
use Illuminate\Database\Eloquent\Builder;

class SupplierController extends BaseController
{
    public function __construct(
        protected SupplierAccountStatementService $statementService
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ListSupplierRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = Supplier::query()
            ->with(['state', 'city.state', 'area', 'acc4'])
            ->withCount([
                'supplyOrders',
                'purchaseInvoices as purchase_invoices_count' => function (Builder $query) {
                    $query->where('status', 'confirmed');
                },
            ])
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('trn', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'))
            ->get();

        $payload = collect(SupplierResource::collection($data)->resolve());

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        if (empty($data['delivery_address']) && ! empty($data['address'])) {
            $data['delivery_address'] = $data['address'];
        }

        $supplier = Supplier::create($data);
        $supplier->load(['state', 'city.state', 'area', 'acc4']);

        return $this->responder(__('messages.api.created'), 201, new SupplierResource($supplier))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Supplier::with(['state', 'city.state', 'area', 'acc4'])
            ->withCount([
                'supplyOrders',
                'purchaseInvoices as purchase_invoices_count' => function (Builder $query) {
                    $query->where('status', 'confirmed');
                },
            ])
            ->findOrFail($id);

        $resource = (new SupplierResource($item))->additional([
            'overview' => $this->overviewPayload($item),
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, $resource)->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, string $id)
    {
        $supplier = Supplier::with(['state', 'city', 'area', 'acc4'])->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('address', $data) && empty($data['delivery_address']) && ! empty($data['address'])) {
            $data['delivery_address'] = $data['address'];
        }

        $supplier->update($data);
        $supplier->refresh()->load(['state', 'city.state', 'area', 'acc4']);

        return $this->responder(__('messages.api.updated'), 200, new SupplierResource($supplier))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Supplier::findOrFail($id);
        abort_if(! $this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();

            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }

    /**
     * Account statement for a supplier (same as the web view page).
     */
    public function accountStatement(SupplierAccountStatementRequest $request, string $id)
    {
        $supplier = Supplier::query()->findOrFail($id);

        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));

        $statement = $this->statementService->build($supplier, $from, $to);

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            new SupplierAccountStatementResource($statement)
        )->respond();
    }

    /**
     * Purchase invoices tab on the supplier view page.
     */
    public function purchaseInvoices(ListSupplierPurchaseInvoicesRequest $request, string $id)
    {
        $supplier = Supplier::query()->findOrFail($id);

        $invoices = Invoice::query()
            ->purchases()
            ->where('temp', false)
            ->where('supplier_id', $supplier->id)
            ->with(['purchasePayments', 'additionalCosts', 'paymentVoucher', 'items'])
            ->withCount('purchasesReturns')
            ->when($request->filled('status'), fn (Builder $builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');
                $builder->where('no', 'like', "%{$search}%");
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest()
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            SupplierPurchaseInvoiceListResource::collection($invoices)
        )->respond();
    }

    /**
     * Supply orders for a supplier (overview card / unused web relation manager).
     */
    public function supplyOrders(ListSupplierSupplyOrdersRequest $request, string $id)
    {
        $supplier = Supplier::query()->findOrFail($id);

        $orders = SupplyOrder::query()
            ->where('supplier_id', $supplier->id)
            ->with('tenant')
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');
                $builder->where(function (Builder $query) use ($search) {
                    $query->where('no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest()
            ->get();

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            SupplierSupplyOrderListResource::collection($orders)
        )->respond();
    }

    /**
     * Summary cards on the supplier overview tab.
     *
     * @return array<string, mixed>
     */
    protected function overviewPayload(Supplier $supplier): array
    {
        $from = now()->startOfYear()->format('Y-m-d');
        $to = now()->format('Y-m-d');
        $statement = $this->statementService->build($supplier, $from, $to);

        if ($statement === null) {
            return [
                'hasAccount' => false,
                'message' => __('fields.supplier_account_statement_no_account'),
                'supplyOrdersCount' => $supplier->supplyOrders()->count(),
                'purchaseInvoicesCount' => $supplier->purchaseInvoices()->where('status', 'confirmed')->count(),
                'unpaidTotal' => 0.0,
                'currentBalance' => 0.0,
                'currency' => main_currency_iso_code(),
            ];
        }

        return [
            'hasAccount' => true,
            'accountCode' => (string) $statement['account_code'],
            'supplyOrdersCount' => (int) $statement['supply_orders_count'],
            'purchaseInvoicesCount' => (int) $statement['purchase_invoices_count'],
            'unpaidTotal' => (float) $statement['unpaid_total'],
            'currentBalance' => (float) $statement['current_balance'],
            'currency' => $statement['currency'],
        ];
    }
}
