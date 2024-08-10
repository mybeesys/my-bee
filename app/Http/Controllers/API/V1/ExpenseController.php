<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListExpensesRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\Acc4Resource;
use App\Http\Resources\ExpenseResource;
use App\Models\Acc4;
use App\Models\Expense;
use App\Models\TaxProfile;
use App\Services\AccountingService;
use App\Services\MathService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ExpenseController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListExpensesRequest $request)
    {
        $data = Expense::with(['category', 'taxProfile', 'debitAccount', 'creditAccount'])
            ->when($request->debit_acc4_code, function (Builder $builder) use ($request) {
                return $builder->whereIn('debit_acc4_code', Arr::wrap($request->debit_acc4_code));
            })
            ->when($request->credit_acc4_code, function (Builder $builder) use ($request) {
                return $builder->whereIn('credit_acc4_code', Arr::wrap($request->credit_acc4_code));
            })
            ->when($request->expense_category_id, function (Builder $builder) use ($request) {
                return $builder->where('expense_category_id', $request->expense_category_id);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('date', $request->from_date, $request->to_date, "d-m-Y");
            })
            ->when($request->min_amount or $request->max_amount, function (Builder $builder) use ($request) {
                return $builder->whereBetween('amount', [$request->min_amount ?? 0, $request->max_amount ?? PHP_INT_MAX]);
            })
            ->orderByDesc('created_at')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, ExpenseResource::collection($data))
            ->filters($request->validated())
            ->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;
        $data['debit_acc4_code'] = "122300001";

        $hasTax = false;
        if ($data['tax_profile_id'] ?? null) {
            $hasTax = true;
            $taxProfile = TaxProfile::find($data['tax_profile_id']);
            $data['tax'] = MathService::instance()->getTaxFromTaxProfile($data['amount'], $taxProfile);
            $data['tax_profile_data'] = $taxProfile->toArray();
        }
        $expense = Expense::create($data);

        if($hasTax and $expense->tax > 0){
            $op = make_taxes_op();
            $accService = new AccountingService();
            $accService
                ->setUp(
                    $op->id,
                    now(),
                    main_currency_iso_code(),
                    generate_double_entry_transaction_id(),
                    $expense->tax,
                    null,
                    'Vat',
                    'Vat',
                    null,
                    meta: ['type' => 'expense', 'id' => $expense->id],
                )->make($expense->credit_acc4_code, $expense->debit_acc4_code)
                ->finish();
        }
        $expense->load(['category', 'debitAccount', 'creditAccount']);

        return $this->responder(__('messages.api.created'), 201, new ExpenseResource($expense))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Expense::with(['category', 'taxProfile', 'debitAccount', 'creditAccount'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, string $id)
    {
        $data = $request->validated();
        $item = Expense::with(['category', 'taxProfile', 'debitAccount', 'creditAccount'])->findOrFail($id);

//        if ($data['tax_profile_id'] ?? null) {
//            $taxProfile = TaxProfile::find($data['tax_profile_id']);
//            $percent = collect($taxProfile->taxes)->sum('percent');
//            $data['tax'] = $percent / 100 * $data['amount'];
//            $data['tax_profile_data'] = $taxProfile->toArray();
//        }else{
//            $data['tax'] = 0;
//            $data['tax_profile_data'] = null;
//        }

        $item->update($data);
        return $this->responder(__('messages.api.updated'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Expense::findOrFail($id);
        abort( 403, __('messages.api.permission_denied'));
////        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
//        try {
//            $item->delete();
//            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
//        } catch (\Exception $exception) {
//            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
//        }
    }

    public function treasuryAccounts()
    {
        $data = Acc4::whereIn('code', [120100001])->OrWhereIn('acc3_code', [1227])->get();

        return $this->responder(__('messages.api.retrieved'), 200, Acc4Resource::collection($data))->respond();
    }

//    public function expenseAccounts()
//    {
//        $data = Acc4::whereHas('acc3', function ($q) {
//            return $q->whereIn('acc2_code', [51, 52, 53]);
//        })->get();
//
//        return $this->responder(__('messages.api.retrieved'), 200, Acc4Resource::collection($data))->respond();
//    }
}
