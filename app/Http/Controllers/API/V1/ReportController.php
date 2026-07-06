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
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ReportController extends BaseController
{

    public function allAccounts(ListAllAccountsReportRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = CashDet::with(['account', 'operation', 'account.acc3', 'currency', 'invoice'])
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
        $items = InvoiceItem::with(['invoice.customer', 'invoice.supplier'])
            ->whereHas('invoice', function (Builder $q) use ($request) {
                $q->when(filled($request->from_date) || filled($request->to_date), function (Builder $builder) use ($request) {
                    $builder->whereDateBetween('date', $request->from_date, $request->to_date, 'd-m-Y');
                })
                    ->when($request->filled('type'), function (Builder $builder) use ($request) {
                        $builder->where('type', $request->type);
                    })
                    ->when($request->filled('invoice_no'), function (Builder $builder) use ($request) {
                        $builder->where('no', $request->invoice_no);
                    })
                    ->when($request->filled('customers'), function (Builder $builder) use ($request) {
                        $builder->whereIn('customer_id', Arr::wrap($request->customers));
                    })
                    ->when($request->filled('suppliers'), function (Builder $builder) use ($request) {
                        $builder->whereIn('supplier_id', Arr::wrap($request->suppliers));
                    });
            })
            ->when($request->filled('products'), function (Builder $builder) use ($request) {
                $builder->whereIn('product_id', Arr::wrap($request->products));
            })
            ->when(filled($request->from_date) || filled($request->to_date), function (Builder $builder) use ($request) {
                $builder->whereDateBetween('created_at', $request->from_date, $request->to_date, 'd-m-Y');
            })
            ->latest('created_at')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, ProductsMovementResource::collection($items))->respond();
    }
}
