<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\StoreVariantLibraryRequest;
use App\Http\Requests\UpdateVariantLibraryRequest;
use App\Http\Resources\VariantLibraryResource;
use App\Models\VariantLibrary;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VariantLibraryController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        $data = VariantLibrary::with(['options'])->get();
        return $this->responder(__('messages.api.retrieved'), 200, VariantLibraryResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVariantLibraryRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->getTenant()->id;

        try {
            DB::beginTransaction();

            $item = VariantLibrary::create(Arr::except($data, ['options']));

            foreach ($data['options'] as $option) {
                $item->options()->create([
                    'tenant_id' => $data['tenant_id'],
                    'variant_library_id' => $item->id,
                    'name_en' => $option['name_en'],
                    'name_ar' => $option['name_ar'],
                ]);
            }

            DB::commit();

            $item->load('options');

            return $this->responder(__('messages.api.created'), 201, new VariantLibraryResource($item))->respond();

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): \Illuminate\Http\JsonResponse
    {
        $item = VariantLibrary::with(['options'])->findOrFail($id);
        return $this->responder(__('messages.api.retrieved'), 200, new VariantLibraryResource($item))->respond();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVariantLibraryRequest $request, string $id): \Illuminate\Http\JsonResponse
    {
        try {

            $data = $request->validated();

            DB::beginTransaction();

            $item = VariantLibrary::with(['options'])->findOrFail($id);
            $item->update(Arr::except($data, ['options']));

            foreach ($data['options'] as $option){
                if(isset($option['id']) and isset($option['name_en']) and isset($option['name_ar'])){
                    $item->options()->where('id', $option['id'])->update([
                        'name_en' => $option['name_en'],
                        'name_ar' => $option['name_ar'],
                    ]);
                }
                if(isset($option['id']) and isset($option['delete']) and $option['delete']){
                    $item->options()->where('id', $option['id'])->delete();
                }
            }

            $item->refresh();

            DB::commit();

            return $this->responder(__('messages.api.updated'), 200, new VariantLibraryResource($item))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): \Illuminate\Http\JsonResponse
    {
        $item = VariantLibrary::findOrFail($id);

        abort_if(!$this->canDelete($item), 403, __('messages.api.permission_denied'));
        try {
            $item->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }
    }
}
