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
}
