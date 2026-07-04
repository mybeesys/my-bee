<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreAcc4Request;
use App\Http\Requests\UpdateAcc4Request;
use App\Http\Resources\Acc4Resource;
use App\Models\Acc4;

class Acc4Controller extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Acc4::query()
            ->excludeInventoryItems()
            ->with(['acc3.acc2.acc1'])
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, Acc4Resource::collection($data))->respond();
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAcc4Request $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;

        $item = Acc4::create($data);

        $item->load(['acc3.acc2.acc1']);

        return $this->responder(__('messages.api.created'), 201, new Acc4Resource($item))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $code)
    {
        $item = Acc4::with(['acc3.acc2.acc1'])->where('code', $code)->firstOrFail();
        return $this->responder(__('messages.api.retrieved'), 200, new Acc4Resource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAcc4Request $request, string $code)
    {
        $item = Acc4::with(['acc3.acc2.acc1'])->where('code', $code)->firstOrFail();
        $item->update($request->only(['name']));
        return $this->responder(__('messages.api.updated'), 200, new Acc4Resource($item))->respond();
    }
}
