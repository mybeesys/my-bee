<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxProfileRequest;
use App\Http\Requests\UpdateTaxProfileRequest;
use App\Http\Resources\TaxProfileResource;
use App\Models\TaxProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TaxProfileController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = TaxProfile::with(['taxes'])->get();
        return $this->responder(__('messages.api.retrieved'), 200, TaxProfileResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaxProfileRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->getTenant()->id;

        try {
            DB::beginTransaction();

            $item = TaxProfile::create(Arr::except($data, ['taxes']));

            foreach ($data['taxes'] as $tax) {
                $item->taxes()->create([
                    'tenant_id' => $data['tenant_id'],
                    'tax_profile_id' => $item->id,
                    'description' => $tax['description'],
                    'percent' => $tax['percent'],
                ]);
            }

            DB::commit();

            $item->load('taxes');

            return $this->responder(__('messages.api.created'), 201, new TaxProfileResource($item))->respond();

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = TaxProfile::with(['taxes'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new TaxProfileResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaxProfileRequest $request, string $id)
    {
        try {

            $data = $request->validated();

            DB::beginTransaction();

            $item = TaxProfile::with(['taxes'])->findOrFail($id);
            $item->update(Arr::except($data, ['taxes']));

            foreach ($data['taxes'] as $tax){
                if(isset($tax['id']) and isset($tax['description']) and isset($tax['percent'])){
                    $item->taxes()->where('id', $tax['id'])->update([
                        'description' => $tax['description'],
                        'percent' => $tax['percent'],
                    ]);
                }
                if(isset($tax['id']) and isset($tax['delete']) and $tax['delete']){
                    $item->taxes()->where('id', $tax['id'])->delete();
                }
            }

            $item->refresh();

            DB::commit();

            return $this->responder(__('messages.api.updated'), 200, new TaxProfileResource($item))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = TaxProfile::findOrFail($id);
        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
