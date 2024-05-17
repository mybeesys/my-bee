<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreExtraRequest;
use App\Http\Requests\UpdateExtraRequest;
use App\Http\Resources\ExtraResource;
use App\Models\ItemExtra;

class ExtraController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = ItemExtra::with(['productsExtras'])->get();
        return $this->responder(__('messages.api.retrieved'), 200, ExtraResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExtraRequest $request)
    {
        $data = $request->only(['name']);

        $data['tenant_id'] = $this->getTenant()->id;

        $item = ItemExtra::create($data);

        return $this->responder(__('messages.api.created'), 201, new ExtraResource($item))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = ItemExtra::with(['productsExtras'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new ExtraResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExtraRequest $request, string $id)
    {
        $item = ItemExtra::with(['productsExtras'])->findOrFail($id);
        $item->update($request->only(['name']));
        return $this->responder(__('messages.api.updated'), 200, new ExtraResource($item))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = ItemExtra::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
