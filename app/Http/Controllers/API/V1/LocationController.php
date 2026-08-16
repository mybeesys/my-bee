<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\AreaResource;
use App\Http\Resources\CityResource;
use App\Http\Resources\CountryResource;
use App\Http\Resources\StateResource;
use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;

class LocationController extends BaseController
{
    public function countries(): \Illuminate\Http\JsonResponse
    {
        $supportedCountryCodes = ["SA"];
        $countries = Country::whereIn('code_alpha2', $supportedCountryCodes)->get();
        return $this->responder(__('messages.api.retrieved'), 200, CountryResource::collection($countries))->respond();
    }

    public function states(): \Illuminate\Http\JsonResponse
    {
        $states = State::with('country')->whereHas('country', function (Builder $q) {
            return $q->where('code_alpha2', 'SA');
        })->get();

        return $this->responder(__('messages.api.retrieved'), 200, StateResource::collection($states))->respond();
    }

    public function cities(): \Illuminate\Http\JsonResponse
    {
        $cities = City::with('areas')->has('areas')->where('state_id', request()->input('state_id'))->get();
        return $this->responder(__('messages.api.retrieved'), 200, CityResource::collection($cities))->respond();
    }

    public function areas(): \Illuminate\Http\JsonResponse
    {
        $areas = Area::where('city_id', request()->input('city_id'))->get();
        return $this->responder(__('messages.api.retrieved'), 200, AreaResource::collection($areas))->respond();
    }

    /**
     * States for customer/supplier forms — same options as PartyContactFormSchema.
     */
    public function tenantStates(): \Illuminate\Http\JsonResponse
    {
        $states = State::query()
            ->withCount('cities')
            ->orderBy('id')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, StateResource::collection($states))->respond();
    }

    /**
     * Cities for a state — all cities (not only those with areas), matching the web form.
     */
    public function tenantCities(): \Illuminate\Http\JsonResponse
    {
        $stateId = request()->input('state_id');

        if (! filled($stateId)) {
            return $this->responder(__('messages.api.retrieved'), 200, [])->respond();
        }

        $cities = City::query()
            ->where('state_id', $stateId)
            ->withCount('areas')
            ->orderBy('id')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, CityResource::collection($cities))->respond();
    }

    /**
     * Areas for a city — matching the web form.
     */
    public function tenantAreas(): \Illuminate\Http\JsonResponse
    {
        $cityId = request()->input('city_id');

        if (! filled($cityId)) {
            return $this->responder(__('messages.api.retrieved'), 200, [])->respond();
        }

        $areas = Area::query()
            ->where('city_id', $cityId)
            ->orderBy('id')
            ->get();

        return $this->responder(__('messages.api.retrieved'), 200, AreaResource::collection($areas))->respond();
    }
}
