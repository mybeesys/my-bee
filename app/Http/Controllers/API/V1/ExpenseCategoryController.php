<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;

class ExpenseCategoryController extends BaseController
{
    public function index()
    {
        $data = ExpenseCategory::query()
            ->with(['expenses'])
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, ExpenseCategoryResource::collection($data))->respond();
    }

    public function store(StoreExpenseCategoryRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->getTenant()->id;

        $expenseCategory = ExpenseCategory::create($data);
        $expenseCategory->setRelation('expenses', collect());
        $expenseCategory->loadCount('expenses');

        return $this->responder(__('messages.api.created'), 201, new ExpenseCategoryResource($expenseCategory))->respond();
    }

    public function show(string $id)
    {
        $item = ExpenseCategory::with(['expenses' => fn ($q) => $q->with(ExpenseService::eagerLoads())])
            ->withCount('expenses')
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new ExpenseCategoryResource($item))->respond();
    }

    public function update(UpdateExpenseCategoryRequest $request, string $id)
    {
        $item = ExpenseCategory::with(['expenses'])->withCount('expenses')->findOrFail($id);
        $item->update($request->validated());
        $item->load(['expenses'])->loadCount('expenses');

        return $this->responder(__('messages.api.updated'), 200, new ExpenseCategoryResource($item))->respond();
    }

    public function destroy(string $id)
    {
        $item = ExpenseCategory::withCount('expenses')->findOrFail($id);

        if ($item->expenses_count > 0) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }

        abort_if(! $this->canDelete($item), 403, __('messages.api.permission_denied'));

        try {
            $item->delete();

            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
