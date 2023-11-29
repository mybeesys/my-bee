<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Expense::with(['category'])->get();
        return $this->responder(__('messages_data_retrieved'), 200, ExpenseResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $expense = Expense::create($data);

        $expense->load('category');

        return $this->responder(__('messages_data_stored'), 201, new ExpenseResource($expense))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Expense::with(['category'])->findOrFail($id);
        return $this->responder(__('messages_data_retrieved'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, string $id)
    {
        $item = Expense::with(['category'])->findOrFail($id);
        $item->update($request->validated());
        return $this->responder(__('messages_data_retrieved'), 200, new ExpenseResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Expense::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, "Permission denied");
        try {
            $item->delete();
            return $this->responder(__('messages_data_deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
