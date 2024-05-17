<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreAdditionalCostTypeRequest;
use App\Http\Requests\UpdateAdditionalCostTypeRequest;
use App\Http\Resources\AdditionalCostTypeResource;
use App\Models\AdditionalCostType;

class AdditionalCostTypeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = AdditionalCostType::all();
        return $this->responder(__('messages.api.retrieved'), 200, AdditionalCostTypeResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdditionalCostTypeRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $item = AdditionalCostType::create($data);

        return $this->responder(__('messages.api.created'), 201, new AdditionalCostTypeResource($item))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = AdditionalCostType::findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new AdditionalCostTypeResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdditionalCostTypeRequest $request, string $id)
    {
        $item = AdditionalCostType::findOrFail($id);
        $item->update($request->validated());
        return $this->responder(__('messages.api.updated'), 200, new AdditionalCostTypeResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = AdditionalCostType::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
