<?php

namespace App\Http\Requests\Concerns;

use App\Models\Area;
use App\Models\City;
use Illuminate\Validation\Validator;

trait ValidatesPartyContactLocation
{
    protected function partyContactLocationRules(): array
    {
        return [
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validatePartyLocationHierarchy($validator);
        });
    }

    protected function validatePartyLocationHierarchy(Validator $validator): void
    {
        $stateId = $this->input('state_id');
        $cityId = $this->input('city_id');
        $areaId = $this->input('area_id');

        if ($cityId && $stateId) {
            $city = City::query()->find($cityId);

            if ($city && (int) $city->state_id !== (int) $stateId) {
                $validator->errors()->add('city_id', __('validation.exists', ['attribute' => 'city_id']));
            }
        }

        if ($areaId && $cityId) {
            $area = Area::query()->find($areaId);

            if ($area && (int) $area->city_id !== (int) $cityId) {
                $validator->errors()->add('area_id', __('validation.exists', ['attribute' => 'area_id']));
            }
        }
    }
}
