<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\GenerateVariantsRequest;
use App\Http\Requests\ListProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\VariantLibraryResource;
use App\Models\Product;
use App\Models\ProductVariantOption;
use App\Models\VariantLibrary;
use App\Models\VariantLibraryOption;
use App\Services\CacheService;
use App\Services\FilamentVariantBuilderService;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

class ProductController extends BaseController
{

    /**
     * Display a listing of the resource.
     */
    public function index(ListProductRequest $request)
    {
        $data = Product::with(['category', 'variantOptions', 'allStocks', 'extras.prices', 'extras.lastPrice', 'lastPrice', 'prices', 'stocks.warehouse', 'taxProfile'])
            ->when($request->name, function (Builder $builder) use ($request) {
                return $builder->where('name', 'LIKE', '%' . $request->name . '%');
            })
            ->when($request->type, function (Builder $builder) use ($request) {
                return $builder->where('type', $request->type);
            })
            ->when($request->barcode, function (Builder $builder) use ($request) {
                return $builder->where('barcode', $request->barcode);
            })
            ->when($request->sku, function (Builder $builder) use ($request) {
                return $builder->where('sku', $request->sku);
            })
            ->when($request->calories_min or $request->calories_max, function (Builder $builder) use ($request) {
                return $builder->whereBetween('calories', [$request->calories_min ?? 0, $request->calories_max ?? PHP_INT_MAX]);
            })
            ->when($request->category_id, function (Builder $builder) use ($request) {
                return $builder->where('category_id', $request->category_id);
            })
            ->when($request->tax_profile_id, function (Builder $builder) use ($request) {
                return $builder->where('tax_profile_id', $request->tax_profile_id);
            })
            ->when(isset($request->published), function (Builder $builder) use ($request) {
                return $builder->where('published', boolval($request->input('published')));
            })
            ->when($request->coupon, function (Builder $builder) use ($request) {
                return $builder->whereRelation('coupon', 'code', $request->coupon);
            })
            ->when($request->from_date or $request->to_date, function (Builder $builder) use ($request) {
                return $builder->whereDateBetween('created_at', $request->input('from_date'), $request->input('to_date'), "d-m-Y");
            })
            ->when($request->sort == 'default' or $request->sort == null, function (Builder $builder) use ($request) {
                return $builder->orderBy('sort');
            })->when($request->sort, function (Builder $builder) use ($request) {
                if ($request->sort == 'oldest')
                    return $builder->oldest();
                return $builder->latest();
            })
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, ProductResource::collection($data))
            ->request($request)
            ->respond();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->getTenant()->id;

        try {
            DB::beginTransaction();

            $product = null;

            if ($data['type'] == "basic") {
                $product = Product::create(Arr::except($data, ['price', 'discount_price', 'images', 'extras', 'variants']));

                PricingService::instance()
                    ->tenant($data['tenant_id'])
                    ->addPrice($product, null, $data['price'], $data['discount_price'] ?? null);

                if ($request->hasFile('images'))
                    $product->addMultipleMediaFromRequest(['images'])
                        ->each(fn(FileAdder $fileAdder) => $fileAdder->toMediaCollection('images'));

            } else {
                if ($request->hasFile('images'))
                    return $this->errorBadRequest()->message("Cannot receive images on type variants this way, please specify image per variant")->respond();

                $product = Product::create(Arr::except($data, ['price', 'discount_price', 'images', 'extras', 'variants']));

                $variant_library_options_ids = collect($data['variants'])->pluck('variant_library_options_ids')->flatten()->toArray();

                $variantLibsIds = VariantLibraryOption::whereIn('id', $variant_library_options_ids)
                    ->get()
                    ->pluck('variant_library_id')
                    ->unique()
                    ->toArray();

                $variantLibs = VariantLibrary::with('options')->findMany($variantLibsIds);

                //save VariantLibraryOption
                foreach ($variantLibs as $lib) {
                    $selectedOptionsIds = $lib->options->whereIn('id', $variant_library_options_ids)->pluck('id')->toArray();
                    ProductVariantOption::create([
                        'tenant_id' => $data['tenant_id'],
                        'product_id' => $product->id,
                        'variant_library_id' => $lib->id,
                        'values' => $selectedOptionsIds,
                    ]);
                }

                foreach ($data['variants'] as $index => $variant) {
                    //save variant
                    //save price
                    $v = $product->variants()->create([
                        'tenant_id' => $data['tenant_id'],
                        'product_id' => $product->id,
                        'variant_library_options_ids' => $variant['variant_library_options_ids'],
                        'sku' => $variant['sku'],
                        'name_ar' => $variant['name_ar'],
                        'name_en' => $variant['name_en'],
                    ]);

                    if ($request->hasFile("variants.$index.image")) {
                        $v->addMediaFromRequest("variants.$index.image")->toMediaCollection('images');
                    }

                    PricingService::instance()
                        ->tenant($data['tenant_id'])
                        ->addPrice($v, null, $variant['price'], $variant['discount_price'] ?? null);

                }
            }

            foreach ($data['extras'] ?? [] as $extra) {
                $productExtra = $product->extras()->create([
                    'tenant_id' => $data['tenant_id'],
                    'item_extra_id' => $extra['id'],
                    'product_id' => $product->id,
                ]);

                PricingService::instance()
                    ->tenant($data['tenant_id'])
                    ->addPrice($productExtra, null, $extra['price'], $extra['discount_price'] ?? null);
            }

            $product->loadMissing(['category', 'variantOptions', 'allStocks', 'extras.prices', 'extras.lastPrice', 'lastPrice', 'prices', 'stocks.warehouse', 'taxProfile']);

            $product->refresh();

            DB::commit();

            return $this->responder(__('messages_data_stored'), 201, new ProductResource($product))->respond();

        } catch (\Throwable $exception) {
            DB::rollBack();
            return $this->error($exception)->respond();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with(['category', 'allStocks', 'extras.prices',
            'extras.lastPrice', 'lastPrice', 'prices', 'stocks.warehouse', 'taxProfile'])->findOrFail($id);

        return $this->responder(__('messages_data_stored'), 201, new ProductResource($product))->respond();
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $id)
    {
        $data = $request->validated();

        $product = Product::findOrFail($id);

        $data['tenant_id'] = $this->getTenant()->id;

        try {
            DB::beginTransaction();

            $variants = $data['variants'] ?? [];

            if (count($variants) > 0) {
                $product->variants()->delete();
                $product->variantOptions()->delete();
            }

            if ($data['type'] == "basic") {
                $product->update(Arr::except($data, ['price', 'discount_price', 'images', 'extras', 'variants']));

                PricingService::instance()
                    ->tenant($data['tenant_id'])
                    ->addPrice($product, null, $data['price'], $data['discount_price'] ?? null);

                if ($request->hasFile('images'))
                    $product->addMultipleMediaFromRequest(['images'])
                        ->each(fn(FileAdder $fileAdder) => $fileAdder->toMediaCollection('images'));

            } else {
                if ($request->hasFile('images'))
                    return $this->errorBadRequest()->message("Cannot receive images on type variants this way, please specify image per variant")->respond();

                $product->update(Arr::except($data, ['price', 'discount_price', 'images', 'extras', 'variants']));

                $variant_library_options_ids = collect($variants)->pluck('variant_library_options_ids')->flatten()->toArray();

                $variantLibsIds = VariantLibraryOption::whereIn('id', $variant_library_options_ids)
                    ->get()
                    ->pluck('variant_library_id')
                    ->unique()
                    ->toArray();

                $variantLibs = VariantLibrary::with('options')->findMany($variantLibsIds);

                //save VariantLibraryOption
                foreach ($variantLibs as $lib) {
                    $selectedOptionsIds = $lib->options->whereIn('id', $variant_library_options_ids)->pluck('id')->toArray();
                    ProductVariantOption::create([
                        'tenant_id' => $data['tenant_id'],
                        'product_id' => $product->id,
                        'variant_library_id' => $lib->id,
                        'values' => $selectedOptionsIds,
                    ]);
                }

                foreach ($variants as $index => $variant) {
                    //save variant
                    //save price
                    $v = $product->variants()->create([
                        'tenant_id' => $data['tenant_id'],
                        'product_id' => $product->id,
                        'variant_library_options_ids' => $variant['variant_library_options_ids'],
                        'sku' => $variant['sku'],
                        'name_ar' => $variant['name_ar'],
                        'name_en' => $variant['name_en'],
                    ]);

                    if ($request->hasFile("variants.$index.image")) {
                        $v->addMediaFromRequest("variants.$index.image")->toMediaCollection('images');
                    }

                    PricingService::instance()
                        ->tenant($data['tenant_id'])
                        ->addPrice($v, null, $variant['price'], $variant['discount_price'] ?? null);

                }
            }

            foreach ($data['extras'] ?? [] as $extra) {
                $productExtra = $product->extras()->firstOrCreate([
                    'item_extra_id' => $extra['id'],
                ], [
                    'tenant_id' => $data['tenant_id'],
                    'item_extra_id' => $extra['id'],
                    'product_id' => $product->id,
                ]);

                PricingService::instance()
                    ->tenant($data['tenant_id'])
                    ->addPrice($productExtra, null, $extra['price'], $extra['discount_price'] ?? null);
            }

            $product->loadMissing(['category', 'variantOptions', 'allStocks', 'extras.prices', 'extras.lastPrice', 'lastPrice', 'prices', 'stocks.warehouse', 'taxProfile']);

            DB::commit();

            return $this->responder(__('messages_data_stored'), 201, new ProductResource($product))->respond();

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
        //
    }


    public function variantsLibrary(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = CacheService::instance()->remember("variants_library", 60 * 3, function () {
            return VariantLibrary::with(['options'])->get();
        }, true);

        return $this->responder(__('messages.api.retrieved'), 200, VariantLibraryResource::collection($data))->respond();
    }

    //pre create or pre edit
    public function generateVariants(GenerateVariantsRequest $request): \Illuminate\Http\JsonResponse
    {
        $selectedOptions = collect($request->variantLibraries)->pluck('selectedOptions')->toArray();
        try {
            $data = FilamentVariantBuilderService::instance(Product::with(['variants'], null)->find($request->product_id), null, $request->product_name)
                ->buildFromGivenVariantsOptionsArray($selectedOptions);

            data_forget($data, '*.tenant_id');
            data_fill($data, '*.price', 0);
            data_fill($data, '*.discount_price', 0);
            data_fill($data, '*.image', null);

            $data = array_values($data); //reset str kys
        } catch (\Throwable $exception) {
//            dd($exception);
            return $this->error($exception)->respond();
        }
        return $this->responder(__('messages.api.retrieved'), 200, $data)->respond();
    }
}
