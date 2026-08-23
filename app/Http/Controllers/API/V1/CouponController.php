<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListCouponsRequest;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CouponController extends BaseController
{
    public function __construct(
        protected CouponService $coupons,
    ) {
    }

    public function index(ListCouponsRequest $request)
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        $sort = $request->input('sort', 'latest');

        $query = Coupon::query()
            ->withCount('usages')
            ->with(CouponService::eagerLoads())
            ->when($request->filled('search'), fn (Builder $builder) => $builder->where('code', 'like', '%'.$request->input('search').'%'))
            ->when($request->has('active'), fn (Builder $builder) => $builder->where('active', $request->boolean('active')))
            ->when($request->filled('span'), fn (Builder $builder) => $builder->where('span', $request->input('span')))
            ->when($request->filled('type'), fn (Builder $builder) => $builder->where('type', $request->input('type')))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'));

        $data = $query->get();
        $payload = collect(CouponResource::collection($data)->resolve());
        $additionalFilters = [];

        if ($request->boolean('include_summaries', true)) {
            $additionalFilters['listSummaries'] = $this->coupons->listSummaries($data);
        }

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200, [], [], $additionalFilters)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload, [], $additionalFilters)->respond();
    }

    public function store(StoreCouponRequest $request)
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        try {
            $coupon = $this->coupons->create(
                $request->validated(),
                (int) $this->getTenantId(),
            );

            return $this->responder(__('messages.api.created'), 201, new CouponResource($coupon))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function show(string $id)
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        $item = Coupon::withCount('usages')
            ->with(CouponService::eagerLoads())
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new CouponResource($item))->respond();
    }

    public function update(UpdateCouponRequest $request, string $id)
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        $item = Coupon::withCount('usages')->findOrFail($id);

        try {
            $coupon = $this->coupons->update($item, $request->validated());

            return $this->responder(__('messages.api.updated'), 200, new CouponResource($coupon))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->error($exception)->respond();
        }
    }

    public function destroy(string $id)
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        Coupon::findOrFail($id);

        return $this->responder(__('messages.api.permission_denied'), 403)->respond();
    }

    public function prefill()
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        return $this->responder(__('messages.api.retrieved'), 200, $this->coupons->prefill())->respond();
    }

    public function formOptions()
    {
        if ($response = $this->ensureStoreEnabled()) {
            return $response;
        }

        return $this->responder(__('messages.api.retrieved'), 200, $this->coupons->formOptions())->respond();
    }

    protected function ensureStoreEnabled()
    {
        if (! plan_allows_store()) {
            return $this->errorBadRequest()
                ->message(__('fields.store_not_available_on_plan'))
                ->respond();
        }

        return null;
    }
}
