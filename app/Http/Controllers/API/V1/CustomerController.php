<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Customer::with(['state', 'city', 'area', 'acc4'])->get();
        return $this->responder(__('messages.api.retrieved'), 200)
            ->paginate($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->getTenant()->id;
        $data['auto_registered'] = false;

        $customer = Customer::create($data);

        return $this->responder(__('messages.api.created'), 201, new CustomerResource($customer))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Customer::with(['state', 'city', 'area', 'acc4'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new CustomerResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, string $id)
    {
        $customer = Customer::with(['state', 'city', 'area', 'acc4'])->findOrFail($id);
        $customer->update($request->validated());
        return $this->responder(__('messages.api.updated'), 200, new CustomerResource($customer))->respond();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Customer::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
