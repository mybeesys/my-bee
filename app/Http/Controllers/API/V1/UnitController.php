<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Unit::with(['products'])->get();
        return $this->responder(__('messages_data_retrieved'), 200, UnitResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUnitRequest $request)
    {
        $data = $request->only(['name']);

        $data['tenant_id'] = $this->getTenant()->id;

        $unit = Unit::create($data);

        return $this->responder(__('messages_data_stored'), 201, new UnitResource($unit))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Unit::with(['products'])->findOrFail($id);
        return $this->responder(__('messages_data_retrieved'), 200, new UnitResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUnitRequest $request, string $id)
    {
        $unit = Unit::with(['products'])->findOrFail($id);
        $unit->update($request->only(['name']));
        return $this->responder(__('messages_data_retrieved'), 200, new UnitResource($unit))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $unit = Unit::findOrFail($id);
        abort_if(!$this->canDelete($unit), 403, "Permission denied");
        try {
            $unit->delete();
            return $this->responder(__('messages_data_deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
