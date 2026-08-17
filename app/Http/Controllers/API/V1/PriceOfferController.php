<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\ListPriceOfferRequest;
use App\Http\Requests\StorePriceOfferRequest;
use App\Http\Requests\UpdatePriceOfferRequest;
use App\Http\Resources\PriceOfferResource;
use App\Models\PriceOffer;
use App\Services\PriceOfferService;
use App\Services\SalesInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class PriceOfferController extends BaseController
{
    public function __construct(
        protected PriceOfferService $priceOffers,
        protected SalesInvoiceService $sales
    ) {
    }

    public function index(ListPriceOfferRequest $request)
    {
        $sort = $request->input('sort', 'latest');

        $data = PriceOffer::query()
            ->with(['customer.acc4', 'customer.state', 'customer.city.state', 'customer.area', 'tenant'])
            ->withCount('details')
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = $request->input('search');

                $builder->where(function (Builder $query) use ($search) {
                    $query->where('no', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('customer_id'), fn (Builder $builder) => $builder->where('customer_id', $request->input('customer_id')))
            ->when($request->input('expiration') === 'active', fn (Builder $builder) => $builder->notExpired())
            ->when($request->input('expiration') === 'expired', fn (Builder $builder) => $builder->expired())
            ->when($request->filled('from_date'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->input('to_date')))
            ->when($sort === 'oldest', fn (Builder $builder) => $builder->orderBy('created_at'))
            ->when($sort !== 'oldest', fn (Builder $builder) => $builder->orderByDesc('created_at'))
            ->get();

        $payload = collect(PriceOfferResource::collection($data)->resolve());

        if ($request->boolean('paginate')) {
            return $this->responder(__('messages.api.retrieved'), 200)->paginate($payload);
        }

        return $this->responder(__('messages.api.retrieved'), 200, $payload)->respond();
    }

    public function store(StorePriceOfferRequest $request)
    {
        if (subscription_resource_maxed_out('price_offers', $this->getTenant(false)->client)) {
            return $this->errorBadRequest()
                ->message(subscription_limit_exceeded_message('price_offers', $this->getTenant(false)->client))
                ->respond();
        }

        try {
            $offer = $this->priceOffers->create(
                $request->validated(),
                $this->getTenant()->id,
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.created'), 201, new PriceOfferResource($offer))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        }
    }

    public function show(string $id)
    {
        $offer = PriceOffer::query()
            ->with(PriceOfferService::eagerLoads())
            ->withCount('details')
            ->findOrFail($id);

        return $this->responder(__('messages.api.retrieved'), 200, new PriceOfferResource($offer))->respond();
    }

    public function update(UpdatePriceOfferRequest $request, string $id)
    {
        $offer = PriceOffer::query()->findOrFail($id);

        try {
            $offer = $this->priceOffers->update(
                $offer,
                $request->validated(),
                $this->getTenant()->id,
                (int) auth('sanctum')->id(),
            );

            return $this->responder(__('messages.api.updated'), 200, new PriceOfferResource($offer))->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        }
    }

    public function destroy(string $id)
    {
        $offer = PriceOffer::query()->findOrFail($id);

        abort_if(! $this->canDelete($offer), 403, __('messages.api.permission_denied'));

        $this->priceOffers->delete($offer);

        return $this->responder(__('messages.api.deleted'), 200, [])->respond();
    }

    /**
     * Prefill for POST sales/commit — does not create an invoice.
     */
    public function salesPrefill(string $id)
    {
        $offer = PriceOffer::query()->findOrFail($id);

        try {
            return $this->responder(
                __('messages.api.retrieved'),
                200,
                $this->sales->priceOfferPrefill($offer)
            )->respond();
        } catch (ValidationException $exception) {
            return $this->responder(__('messages.api.validation_error'), 422, [], $exception->errors())->respond();
        }
    }
}
