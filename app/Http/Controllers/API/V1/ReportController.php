<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListAllAccountsReportRequest;
use App\Http\Requests\ListBankReportRequest;
use App\Http\Requests\ListTaxReportRequest;
use App\Http\Requests\ListTrasuryReportRequest;
use App\Http\Resources\CashDetReportResource;
use App\Http\Resources\ProductsMovementResource;
use App\Models\CashDet;
use App\Services\ProductMovementBalanceService;
use App\Services\ProductsMovementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ReportController extends BaseController
{

    public function allAccounts(ListAllAccountsReportRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = CashDet::with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->whereHas('account', fn (Builder $query) => $query->ledgerAccounts())
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->account_code, function (Builder $builder) use ($request) {
                return $builder->where('account_code', $request->account_code);
            })->get();

        return $this->responder(__('messages.api.retrieved'), 200, CashDetReportResource::collection($data))->respond();
    }

    public function bankAccount(ListBankReportRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = CashDet::with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->account_code, function (Builder $builder) use ($request) {
                return $builder->where('account_code', $request->account_code);
            })
            ->whereHas('account', function (Builder $q) {
                $q->where('acc3_code', '1227');
            })->get();

        return $this->responder(__('messages.api.retrieved'), 200, CashDetReportResource::collection($data))->respond();
    }

    public function treasuryAccount(ListTrasuryReportRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = CashDet::with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->account_code, function (Builder $builder) use ($request) {
                return $builder->where('account_code', $request->account_code);
            })
            ->whereHas('account', function (Builder $q) {
                $q->where('acc3_code', '1201');
            })->get();

        return $this->responder(__('messages.api.retrieved'), 200, CashDetReportResource::collection($data))->respond();
    }

    public function taxAccount(ListTaxReportRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = CashDet::with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->account_code, function (Builder $builder) use ($request) {
                return $builder->where('account_code', $request->account_code);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->whereHas('account', function (Builder $q) {
                $q->where('acc3_code', '1201');
            })->get();
        return $this->responder(__('messages.api.retrieved'), 200, CashDetReportResource::collection($data))->respond();
    }

    public function productsMovements(Request $request): \Illuminate\Http\JsonResponse
    {
        $filters = [
            'type' => $request->filled('type') ? $request->type : null,
            'customers' => $request->filled('customers') ? Arr::wrap($request->customers) : null,
            'suppliers' => $request->filled('suppliers') ? Arr::wrap($request->suppliers) : null,
            'products' => $request->filled('products') ? Arr::wrap($request->products) : null,
            'created_from' => filled($request->from_date)
                ? Carbon::createFromFormat('d-m-Y', $request->from_date)->toDateString()
                : null,
            'created_until' => filled($request->to_date)
                ? Carbon::createFromFormat('d-m-Y', $request->to_date)->toDateString()
                : null,
        ];

        if ($request->filled('invoice_no')) {
            $invoiceIds = \App\Models\Invoice::query()
                ->where('no', $request->invoice_no)
                ->pluck('id')
                ->all();

            $filters['invoices'] = $invoiceIds ?: [0];
        }

        $records = app(ProductsMovementService::class)->toRecords(
            app(ProductsMovementService::class)->build($filters)
        );

        app(ProductMovementBalanceService::class)->preloadForMovementLines($records);

        return $this->responder(__('messages.api.retrieved'), 200, ProductsMovementResource::collection($records))->respond();
    }
}
