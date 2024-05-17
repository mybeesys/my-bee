<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListAllAccountsReportRequest;
use App\Http\Requests\ListBankReportRequest;
use App\Http\Requests\ListTaxReportRequest;
use App\Http\Requests\ListTrasuryReportRequest;
use App\Http\Resources\BankReportResource;
use App\Http\Resources\CashDetReportResource;
use App\Models\CashDet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
            ->whereHas('account', function (Builder $q) {
                $q->where('acc3_code', '1201');
            })->get();
        return $this->responder(__('messages.api.retrieved'), 200, CashDetReportResource::collection($data))->respond();
    }
}
