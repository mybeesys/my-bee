<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\IncomeStatementReportRequest;
use App\Http\Requests\InventoryDetailReportRequest;
use App\Http\Requests\InventorySummaryReportRequest;
use App\Http\Requests\ListAllAccountsReportRequest;
use App\Http\Requests\ListBankReportRequest;
use App\Http\Requests\ListProductsMovementsReportRequest;
use App\Http\Requests\ListTaxReportRequest;
use App\Http\Requests\ListTrasuryReportRequest;
use App\Http\Requests\SalesStatementReportRequest;
use App\Http\Resources\CashDetReportResource;
use App\Http\Resources\ProductsMovementResource;
use App\Models\Acc4;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\CashDetReportQueryService;
use App\Services\IncomeStatementService;
use App\Services\InventoryReportService;
use App\Services\ProductMovementBalanceService;
use App\Services\ProductsMovementService;
use App\Services\SalesStatementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ReportController extends BaseController
{
    public function __construct(
        protected CashDetReportQueryService $cashDetReports,
        protected IncomeStatementService $incomeStatements,
        protected SalesStatementService $salesStatements,
        protected InventoryReportService $inventoryReports,
        protected ProductsMovementService $productsMovements,
        protected ProductMovementBalanceService $productMovementBalances,
    ) {
    }

    /**
     * Catalog of tenant reports + filter definitions for mobile UI.
     */
    public function catalog(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'reports' => [
                [
                    'key' => 'account_statement',
                    'label' => __('fields.account_statement'),
                    'endpoint' => 'reports/account/statement/all',
                    'filtersEndpoint' => 'reports/filters/account-statement',
                ],
                [
                    'key' => 'treasury',
                    'label' => __('fields.treasury_report'),
                    'endpoint' => 'reports/account/statement/treasury',
                    'filtersEndpoint' => 'reports/filters/treasury',
                ],
                [
                    'key' => 'bank',
                    'label' => __('fields.bank_report'),
                    'endpoint' => 'reports/account/statement/bank',
                    'filtersEndpoint' => 'reports/filters/bank',
                ],
                [
                    'key' => 'tax',
                    'label' => __('fields.tax_report'),
                    'endpoint' => 'reports/account/statement/tax',
                    'filtersEndpoint' => 'reports/filters/tax',
                ],
                [
                    'key' => 'income_statement',
                    'label' => __('fields.income_statement'),
                    'endpoint' => 'reports/income-statement',
                    'filtersEndpoint' => 'reports/filters/income-statement',
                ],
                [
                    'key' => 'products_movement',
                    'label' => __('fields.products_movement'),
                    'endpoint' => 'reports/account/statement/products-movements',
                    'filtersEndpoint' => 'reports/filters/products-movements',
                ],
                [
                    'key' => 'sales_statement',
                    'label' => __('fields.sales_statement_report'),
                    'endpoint' => 'reports/sales-statement',
                    'filtersEndpoint' => 'reports/filters/sales-statement',
                ],
                [
                    'key' => 'inventory_detail',
                    'label' => __('fields.inventory_detail_report'),
                    'endpoint' => 'reports/inventory/detail',
                    'filtersEndpoint' => 'reports/filters/inventory-detail',
                ],
                [
                    'key' => 'inventory_summary',
                    'label' => __('fields.inventory_summary_report'),
                    'endpoint' => 'reports/inventory/summary',
                    'filtersEndpoint' => 'reports/filters/inventory-summary',
                ],
            ],
            'dateFormat' => 'd-m-Y',
            'dateFormatAlt' => 'Y-m-d',
            'defaults' => [
                'from_date' => now()->startOfYear()->format('d-m-Y'),
                'to_date' => now()->format('d-m-Y'),
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->toDateString(),
            ],
        ])->respond();
    }

    public function filtersAccountStatement(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'accounts' => $this->optionsMap(Acc4::ledgerAccountOptions()),
            'filters' => [
                ['key' => 'account_code', 'type' => 'select', 'required' => true, 'label' => __('fields.account'), 'optionsKey' => 'accounts'],
                ['key' => 'op_id', 'type' => 'integer', 'required' => false, 'label' => __('fields.voucher_no')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from')],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until')],
            ],
            'notes' => [
                'account_code is required to return rows (same as web).',
                'Accounts include: other parties, customers, suppliers, treasury, and banks (ledgerAccountOptions).',
            ],
        ])->respond();
    }

    public function filtersTreasury(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => [
                ['key' => 'account_code', 'type' => 'string', 'required' => false, 'label' => __('fields.account')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from')],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until')],
            ],
            'scope' => ['acc3_code' => '1201', 'item_type' => null],
        ])->respond();
    }

    public function filtersBank(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'accounts' => $this->optionsMap(
                Acc4::query()->bankAccounts()->orderBy('name')->pluck('name', 'code')->all()
            ),
            'filters' => [
                ['key' => 'account_code', 'type' => 'select', 'required' => false, 'label' => __('fields.account'), 'optionsKey' => 'accounts'],
                ['key' => 'op_id', 'type' => 'integer', 'required' => false, 'label' => __('fields.voucher_no')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from')],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until')],
            ],
            'scope' => ['acc3_code' => '1227', 'item_type' => null],
        ])->respond();
    }

    public function filtersTax(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => [
                ['key' => 'account_code', 'type' => 'string', 'required' => false, 'label' => __('fields.account')],
                ['key' => 'op_id', 'type' => 'integer', 'required' => false, 'label' => __('fields.voucher_no')],
                ['key' => 'transaction_id', 'type' => 'string', 'required' => false, 'label' => __('fields.transaction_id')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from')],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until')],
            ],
            'scope' => ['acc3_code' => '1228'],
        ])->respond();
    }

    public function filtersIncomeStatement(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => [
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.income_statement_period'), 'alias' => 'from (Y-m-d)'],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.income_statement_period_to'), 'alias' => 'to (Y-m-d)'],
            ],
            'defaults' => [
                'from_date' => now()->startOfYear()->format('d-m-Y'),
                'to_date' => now()->format('d-m-Y'),
            ],
        ])->respond();
    }

    public function filtersProductsMovements(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'types' => [
                ['value' => 'purchases', 'label' => __('fields.products_movements_type_purchases')],
                ['value' => 'sales', 'label' => __('fields.products_movements_type_sales')],
                ['value' => 'sales_return', 'label' => __('fields.products_movements_type_sales_return')],
                ['value' => 'purchase_return', 'label' => __('fields.products_movements_type_purchase_return')],
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->name,
            ])->values(),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn ($s) => [
                'value' => $s->id,
                'label' => $s->name,
            ])->values(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->name,
            ])->values(),
            'filters' => [
                ['key' => 'type', 'type' => 'select', 'required' => false, 'optionsKey' => 'types', 'label' => __('fields.type')],
                ['key' => 'customers', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'customers', 'label' => __('fields.clients')],
                ['key' => 'suppliers', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'suppliers', 'label' => __('fields.suppliers')],
                ['key' => 'products', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'products', 'label' => __('fields.products')],
                ['key' => 'invoices', 'type' => 'multi_select', 'required' => false, 'label' => __('fields.invoice_no')],
                ['key' => 'invoice_no', 'type' => 'string', 'required' => false, 'label' => __('fields.invoice_no')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from')],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until')],
            ],
        ])->respond();
    }

    public function filtersSalesStatement(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'groupBy' => [
                ['value' => 'product', 'label' => __('fields.sales_statement_group_product')],
                ['value' => 'invoice', 'label' => __('fields.sales_statement_group_invoice')],
            ],
            'lineTypes' => [
                ['value' => 'sale', 'label' => __('fields.sales_statement_line_sale')],
                ['value' => 'return', 'label' => __('fields.sales_statement_line_return')],
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                'value' => $c->id,
                'label' => $c->name,
            ])->values(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name'])->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->name,
            ])->values(),
            'filters' => [
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => true, 'label' => __('fields.sales_statement_from'), 'alias' => 'from (Y-m-d)'],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => true, 'label' => __('fields.sales_statement_to'), 'alias' => 'to (Y-m-d)'],
                ['key' => 'group_by', 'type' => 'select', 'required' => false, 'default' => 'product', 'optionsKey' => 'groupBy', 'label' => __('fields.sales_statement_group_by')],
                ['key' => 'line_types', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'lineTypes', 'label' => __('fields.sales_statement_movement_type')],
                ['key' => 'customer_ids', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'customers', 'label' => __('fields.clients')],
                ['key' => 'product_ids', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'products', 'label' => __('fields.products')],
            ],
        ])->respond();
    }

    public function filtersInventoryDetail(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku'])->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->name,
                'sku' => $p->sku,
            ])->values(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name'])->map(fn ($w) => [
                'value' => $w->id,
                'label' => $w->name,
            ])->values(),
            'movementTypes' => $this->inventoryMovementTypeOptions(detail: true),
            'filters' => [
                ['key' => 'product_id', 'type' => 'select', 'required' => true, 'optionsKey' => 'products', 'label' => __('fields.product')],
                ['key' => 'warehouse_id', 'type' => 'select', 'required' => true, 'optionsKey' => 'warehouses', 'label' => __('fields.warehouse')],
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from'), 'alias' => 'from (Y-m-d)'],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until'), 'alias' => 'to (Y-m-d)'],
                ['key' => 'movement_types', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'movementTypes', 'label' => __('fields.type')],
            ],
        ])->respond();
    }

    public function filtersInventorySummary(): JsonResponse
    {
        return $this->responder(__('messages.api.retrieved'), 200, [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'sku'])->map(fn ($p) => [
                'value' => $p->id,
                'label' => $p->name,
                'sku' => $p->sku,
            ])->values(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(['id', 'name'])->map(fn ($w) => [
                'value' => $w->id,
                'label' => $w->name,
            ])->values(),
            'movementTypes' => $this->inventoryMovementTypeOptions(detail: false),
            'filters' => [
                ['key' => 'from_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_from'), 'alias' => 'from (Y-m-d)'],
                ['key' => 'to_date', 'type' => 'date', 'format' => 'd-m-Y', 'required' => false, 'label' => __('fields.created_until'), 'alias' => 'to (Y-m-d)'],
                ['key' => 'warehouse_ids', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'warehouses', 'label' => __('fields.warehouses')],
                ['key' => 'product_ids', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'products', 'label' => __('fields.products')],
                ['key' => 'movement_types', 'type' => 'multi_select', 'required' => false, 'optionsKey' => 'movementTypes', 'label' => __('fields.type')],
            ],
        ])->respond();
    }

    public function allAccounts(ListAllAccountsReportRequest $request): JsonResponse
    {
        $data = $this->cashDetReports->query(
            $request,
            fn (Builder $q) => $q->ledgerAccounts(),
            requireAccountCode: true,
        )->get();

        return $this->respondCashDet($data, $request);
    }

    public function bankAccount(ListBankReportRequest $request): JsonResponse
    {
        $data = $this->cashDetReports->query(
            $request,
            fn (Builder $q) => $q->whereNull('item_type')->where('acc3_code', '1227'),
        )->get();

        return $this->respondCashDet($data, $request);
    }

    public function treasuryAccount(ListTrasuryReportRequest $request): JsonResponse
    {
        $data = $this->cashDetReports->query(
            $request,
            fn (Builder $q) => $q->whereNull('item_type')->where('acc3_code', '1201'),
        )->get();

        return $this->respondCashDet($data, $request);
    }

    public function taxAccount(ListTaxReportRequest $request): JsonResponse
    {
        $data = $this->cashDetReports->query(
            $request,
            fn (Builder $q) => $q->where('acc3_code', '1228'),
        )->get();

        return $this->respondCashDet($data, $request);
    }

    public function productsMovements(ListProductsMovementsReportRequest $request): JsonResponse
    {
        $filters = [
            'type' => $request->filled('type') ? $request->input('type') : null,
            'customers' => $request->filled('customers') ? Arr::wrap($request->input('customers')) : null,
            'suppliers' => $request->filled('suppliers') ? Arr::wrap($request->input('suppliers')) : null,
            'products' => $request->filled('products') ? Arr::wrap($request->input('products')) : null,
            'invoices' => $request->filled('invoices') ? Arr::wrap($request->input('invoices')) : null,
            'created_from' => CashDetReportQueryService::parseApiDate($request->input('from_date')),
            'created_until' => CashDetReportQueryService::parseApiDate($request->input('to_date')),
        ];

        if ($request->filled('invoice_no') && empty($filters['invoices'])) {
            $invoiceIds = Invoice::query()
                ->where('no', $request->input('invoice_no'))
                ->pluck('id')
                ->all();

            $filters['invoices'] = $invoiceIds ?: [0];
        }

        $records = $this->productsMovements->toRecords(
            $this->productsMovements->build($filters)
        );

        $this->productMovementBalances->preloadForMovementLines($records);

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            ProductsMovementResource::collection($records)
        )->respond();
    }

    public function incomeStatement(IncomeStatementReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request);

        $report = $this->incomeStatements->build($from, $to);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'from' => $report['from'],
            'to' => $report['to'],
            'currency' => $report['currency'],
            'salesTotal' => (float) $report['sales_total'],
            'purchasesTotal' => (float) $report['purchases_total'],
            'expensesTotal' => (float) $report['expenses_total'],
            'netIncome' => (float) $report['net_income'],
            'salesGross' => (float) $report['sales_gross'],
            'salesReturns' => (float) $report['sales_returns'],
            'purchasesGross' => (float) $report['purchases_gross'],
            'purchaseReturns' => (float) $report['purchase_returns'],
            'salesInvoicesCount' => (int) $report['sales_invoices_count'],
            'purchaseInvoicesCount' => (int) $report['purchase_invoices_count'],
            'sections' => [
                'sales' => [
                    'label' => __('fields.income_statement_sales_section'),
                    'total' => (float) $report['sales_total'],
                    'lines' => $report['sales_lines']->map(fn ($line) => [
                        'code' => $line->code,
                        'name' => $line->name,
                        'net' => (float) $line->net,
                    ])->values(),
                ],
                'purchases' => [
                    'label' => __('fields.income_statement_purchases_section'),
                    'total' => (float) $report['purchases_total'],
                    'lines' => $report['purchases_lines']->map(fn ($line) => [
                        'code' => $line->code,
                        'name' => $line->name,
                        'net' => (float) $line->net,
                    ])->values(),
                ],
                'expenses' => [
                    'label' => __('fields.income_statement_expense_section'),
                    'total' => (float) $report['expenses_total'],
                    'lines' => $report['expense_lines']->map(fn ($line) => [
                        'code' => $line->code,
                        'name' => $line->name,
                        'net' => (float) $line->net,
                    ])->values(),
                ],
            ],
            'summary' => [
                'label' => __('fields.income_statement_summary'),
                'netIncomeLabel' => __('fields.income_statement_net_income'),
                'netIncome' => (float) $report['net_income'],
            ],
        ])->respond();
    }

    public function salesStatement(SalesStatementReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request, defaultYearStart: true);

        $report = $this->salesStatements->build([
            'from' => $from,
            'to' => $to,
            'group_by' => $request->input('group_by', 'product'),
            'line_types' => Arr::wrap($request->input('line_types', [])),
            'customer_ids' => Arr::wrap($request->input('customer_ids', [])),
            'product_ids' => Arr::wrap($request->input('product_ids', [])),
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => $this->camelKeys($report['filters']),
            'stats' => $this->camelKeys($report['stats']),
            'lines' => collect($report['lines'])->map(fn (array $line) => $this->camelKeys($line))->values(),
        ])->respond();
    }

    public function inventoryDetail(InventoryDetailReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request, defaultYearStart: true);

        $report = $this->inventoryReports->buildDetail([
            'from' => $from,
            'to' => $to,
            'product_id' => (int) $request->input('product_id'),
            'warehouse_id' => (int) $request->input('warehouse_id'),
            'movement_types' => Arr::wrap($request->input('movement_types', [])),
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => $this->camelKeys($report['filters']),
            'product' => $report['product'] ? [
                'id' => $report['product']->id,
                'name' => $report['product']->name,
                'sku' => $report['product']->sku,
            ] : null,
            'warehouse' => $report['warehouse'] ? [
                'id' => $report['warehouse']->id,
                'name' => $report['warehouse']->name,
            ] : null,
            'stats' => $report['stats'] ? $this->camelKeys($report['stats']) : null,
            'lines' => collect($report['lines'])->map(fn (array $line) => $this->camelKeys($line))->values(),
        ])->respond();
    }

    public function inventorySummary(InventorySummaryReportRequest $request): JsonResponse
    {
        [$from, $to] = $this->resolvePeriod($request, defaultYearStart: true);

        $report = $this->inventoryReports->buildSummary([
            'from' => $from,
            'to' => $to,
            'warehouse_ids' => Arr::wrap($request->input('warehouse_ids', [])),
            'product_ids' => Arr::wrap($request->input('product_ids', [])),
            'movement_types' => Arr::wrap($request->input('movement_types', [])),
        ]);

        return $this->responder(__('messages.api.retrieved'), 200, [
            'filters' => $this->camelKeys($report['filters']),
            'rows' => collect($report['rows'])->map(fn (array $row) => $this->camelKeys($row))->values(),
        ])->respond();
    }

    protected function respondCashDet($data, Request $request): JsonResponse
    {
        $totals = [
            'debit' => round((float) $data->sum('amount_in'), currency_decimals()),
            'credit' => round((float) $data->sum('amount_out'), currency_decimals()),
            'count' => $data->count(),
            'currency' => main_currency_iso_code(),
        ];

        return $this->responder(
            __('messages.api.retrieved'),
            200,
            CashDetReportResource::collection($data),
            [],
            [
                'totals' => $totals,
                'applied' => [
                    'accountCode' => $request->input('account_code'),
                    'opId' => $request->input('op_id'),
                    'transactionId' => $request->input('transaction_id'),
                    'fromDate' => $request->input('from_date'),
                    'toDate' => $request->input('to_date'),
                ],
            ]
        )->respond();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function resolvePeriod(Request $request, bool $defaultYearStart = false): array
    {
        $from = CashDetReportQueryService::parseApiDate($request->input('from_date'))
            ?? CashDetReportQueryService::parseApiDate($request->input('from'));

        $to = CashDetReportQueryService::parseApiDate($request->input('to_date'))
            ?? CashDetReportQueryService::parseApiDate($request->input('to'));

        if ($defaultYearStart) {
            $from ??= now()->startOfYear()->toDateString();
            $to ??= now()->toDateString();
        }

        return [$from, $to];
    }

    /**
     * @param  array<string|int, string>  $map
     * @return array<int, array{value: string, label: string}>
     */
    protected function optionsMap(array $map): array
    {
        return collect($map)
            ->map(fn ($label, $value) => [
                'value' => (string) $value,
                'label' => (string) $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function inventoryMovementTypeOptions(bool $detail): array
    {
        $options = [
            InventoryReportService::TYPE_PURCHASE => __('fields.inventory_movement_purchase'),
            InventoryReportService::TYPE_SALES => __('fields.inventory_movement_sales'),
            InventoryReportService::TYPE_PURCHASE_RETURN => __('fields.inventory_movement_purchase_return'),
            InventoryReportService::TYPE_SALES_RETURN => __('fields.inventory_movement_sales_return'),
        ];

        if (! $detail) {
            $options = [
                InventoryReportService::TYPE_OPENING => __('fields.inventory_movement_opening'),
                ...$options,
                InventoryReportService::TYPE_TRANSFER_IN => __('fields.inventory_movement_transfer_in'),
                InventoryReportService::TYPE_TRANSFER_OUT => __('fields.inventory_movement_transfer_out'),
            ];
        }

        return collect($options)->map(fn ($label, $value) => [
            'value' => (string) $value,
            'label' => (string) $label,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function camelKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $camel = is_string($key)
                ? lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))))
                : $key;

            if (is_array($value) && Arr::isAssoc($value)) {
                $result[$camel] = $this->camelKeys($value);
            } elseif (is_array($value)) {
                $result[$camel] = collect($value)->map(function ($item) {
                    return is_array($item) && Arr::isAssoc($item) ? $this->camelKeys($item) : $item;
                })->all();
            } else {
                $result[$camel] = $value;
            }
        }

        return $result;
    }
}
