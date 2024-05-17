<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Http\Resources\ServiceTypeResource;
use App\Models\ServiceType;

class ServiceTypeController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = ServiceType::all();
        return $this->responder(__('messages.api.retrieved'), 200, ServiceTypeResource::collection($data))->respond();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceTypeRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $item = ServiceType::create($data);

        return $this->responder(__('messages.api.created'), 201, new ServiceTypeResource($item))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = ServiceType::findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new ServiceTypeResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceTypeRequest $request, string $id)
    {
        $item = ServiceType::findOrFail($id);
        $item->update($request->validated());
        return $this->responder(__('messages.api.updated'), 200, new ServiceTypeResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = ServiceType::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
