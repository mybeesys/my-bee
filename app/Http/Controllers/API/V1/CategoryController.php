<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CategoryController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Category::with(['products', 'parent'])->orderBy('sort')->get();
        return $this->responder(__('messages.api.retrieved'), 200, CategoryResource::collection($data))->respond();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        $parent = Category::with('products')->find($data['parent_id'] ?? null);

        if ($parent and $parent->products->isNotEmpty())
            abort(400, 'The selected parent category has products and cannot be a parent or is invalid');

        $data['name'] = [
            'en' => $data['name_en'],
            'ar' => $data['name_ar'],
        ];

        $data['tenant_id'] = $this->getTenant()->id;

        $cat = Category::create(Arr::except($data, ['name_en', 'name_ar']));

        $cat->load(['products', 'parent']);

        return $this->responder(__('messages.api.created'), 201, new CategoryResource($cat))->respond();
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $cat = Category::with(['products', 'parent'])->find($id);
        return $this->responder(__('messages.api.retrieved'), 200, new CategoryResource($cat))->respond();
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, string $id)
    {
        $data = $request->validated();

        $category = Category::with('products')->findOrFail($id);

        $parent = Category::with('products')->find($data['parent_id'] ?? null);

        if (!$parent)
            unset($data['parent_id']);

        if ($parent and $parent->products->isNotEmpty() or ($parent and $parent->id == $category->id))
            abort(400, 'The selected parent category has products and cannot be a parent or is invalid');

        $data['name'] = [
            'en' => $data['name_en'],
            'ar' => $data['name_ar'],
        ];

        if (array_key_exists('name_en', $data)) {
            $category->setTranslation('name', 'en', $data['name_en']);
        }

        if (array_key_exists('name_ar', $data)) {
            $category->setTranslation('name', 'ar', $data['name_ar']);
        }

        $data['tenant_id'] = $this->getTenantId();

        $category->update(Arr::except($data, ['name_en', 'name_ar']));

        $category->load(['products', 'parent']);

        return $this->responder(__('messages.api.updated'), 200, new CategoryResource($category))->respond();

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        abort_if(!$this->canDelete($category), 403, __('messages.api.permission_denied'));
        try {
            $category->delete();
            return $this->responder(__('messages.api.deleted'), 200, [])->respond();
        } catch (\Exception $exception) {
            return $this->responder(__('fields.record_in_use_alert'), 400)->respond();
        }

    }
}
