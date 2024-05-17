<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;

class ExpenseCategoryController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = ExpenseCategory::with(['expenses'])->get();
        return $this->responder(__('messages.api.retrieved'), 200, ExpenseCategoryResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseCategoryRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $expenseCategory = ExpenseCategory::create($data);

        return $this->responder(__('messages.api.created'), 201, new ExpenseCategoryResource($expenseCategory))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = ExpenseCategory::with(['expenses'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new ExpenseCategoryResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseCategoryRequest $request, string $id)
    {
        $item = ExpenseCategory::with(['expenses'])->findOrFail($id);
        $item->update($request->validated());
        return $this->responder(__('messages.api.updated'), 200, new ExpenseCategoryResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = ExpenseCategory::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
