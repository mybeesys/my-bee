<?php

namespace App\Http\Controllers\API\V1;


use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Supplier::with('acc4')->get();
        return $this->responder(__('messages.api.retrieved'), 200, SupplierResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $supplier = Supplier::create($data);

        $supplier->load('acc4');

        return $this->responder(__('messages.api.created'), 201, new SupplierResource($supplier))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Supplier::with('acc4')->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new SupplierResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, string $id)
    {
        $supplier = Supplier::with('acc4')->findOrFail($id);
        $supplier->update($request->validated());
        return $this->responder(__('messages.api.updated'), 200, new SupplierResource($supplier))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Supplier::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
