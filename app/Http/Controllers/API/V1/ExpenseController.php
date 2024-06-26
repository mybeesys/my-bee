<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListExpensesRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\TaxProfile;
use App\Services\MathService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ExpenseController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListExpensesRequest $request)
    {
        $data = Expense::with(['category', 'taxProfile'])
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

        if ($data['tax_profile_id'] ?? null) {
            $taxProfile = TaxProfile::find($data['tax_profile_id']);
            $data['tax'] = MathService::instance()->getTaxFromTaxProfile($data['amount'], $taxProfile);
            $data['tax_profile_data'] = $taxProfile->toArray();
        }
        $expense = Expense::create($data);

        $expense->load('category');

        return $this->responder(__('messages.api.created'), 201, new ExpenseResource($expense))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Expense::with(['category', 'taxProfile'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, string $id)
    {
        $data = $request->validated();
        $item = Expense::with(['category', 'taxProfile'])->findOrFail($id);

        if ($data['tax_profile_id'] ?? null) {
            $taxProfile = TaxProfile::find($data['tax_profile_id']);
            $percent = collect($taxProfile->taxes)->sum('percent');
            $data['tax'] = $percent / 100 * $data['amount'];
            $data['tax_profile_data'] = $taxProfile->toArray();
        }else{
            $data['tax'] = 0;
            $data['tax_profile_data'] = null;
        }

        $item->update($data);
        return $this->responder(__('messages.api.updated'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Expense::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
