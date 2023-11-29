<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Warehouse::with(['stocks.item'])->get();
        return $this->responder(__('messages_data_retrieved'), 200, WarehouseResource::collection($data))->respond();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $warehouse = Warehouse::create($data);

        return $this->responder(__('messages_data_stored'), 201, new WarehouseResource($warehouse))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Warehouse::findOrFail($id);
        return $this->responder(__('messages_data_retrieved'), 200, new WarehouseResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseRequest $request, string $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($request->validated());
        return $this->responder(__('messages_data_retrieved'), 200, new WarehouseResource($warehouse))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Warehouse::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, "Permission denied");
        try {
            $item->delete();
            return $this->responder(__('messages_data_deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
